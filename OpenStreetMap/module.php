<?php

declare(strict_types=1);

// CLASS OpenStreetMap
class OpenStreetMap extends IPSModule
{

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(1);

        $this->RegisterPropertyInteger('LocationControlID', 0);
        $this->RegisterPropertyInteger('MapZoom', 13);
        $this->RegisterPropertyString('HouseLocation', json_encode([
            'latitude' => 50.00,
            'longitude' => 10.00,
            'zoom' => 6
        ]));
        $this->RegisterPropertyString('Points', '[]');
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     */
    public function Destroy()
    {
        parent::Destroy();
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        // Set status
        $this->SetStatus(102);
    }



    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     */
    public function GetVisualizationTile()
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // The payload is encoded here so it can be injected safely into the HTML
        $handling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $handling;
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     */
    private function GetFullUpdateMessage()
    {
        $payload = [
            'house' => $this->GetHouseLocation(),
            'points' => $this->GetConfiguredPoints()
        ];

        $this->SendDebug(__FUNCTION__, json_encode($payload), 0);

        return $payload;
    }

    private function GetHouseLocation(): array
    {
        $locationControl = $this->GetLocationFromControl();
        if ($locationControl !== null) {
            return $locationControl;
        }

        return $this->GetManualHouseLocation();
    }

    private function GetLocationFromControl(): ?array
    {
        $locationID = $this->ReadPropertyInteger('LocationControlID');
        if ($locationID <= 0 || !IPS_InstanceExists($locationID)) {
            return null;
        }

        $coordinates = $this->ResolveCoordinatesFromInstance($locationID);
        if ($coordinates === null) {
            return null;
        }

        $zoom = $this->NormalizeZoom($this->ReadPropertyInteger('MapZoom'));
        $label = "Zuhause";

        return [
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'zoom' => $zoom,
            'label' => $label
        ];
    }

    private function GetManualHouseLocation(): array
    {
        $raw = json_decode($this->ReadPropertyString('HouseLocation'), true);

        $latitude = 50.00;
        $longitude = 10.00;
        $zoom = $this->NormalizeZoom($this->ReadPropertyInteger('MapZoom'));
        $label = 'Haus';

        if (is_array($raw)) {
            if (isset($raw['latitude']) || isset($raw['lat']) || isset($raw['y'])) {
                $latitude = (float)($raw['latitude'] ?? $raw['lat'] ?? $raw['y']);
            }
            if (isset($raw['longitude']) || isset($raw['lng']) || isset($raw['x'])) {
                $longitude = (float)($raw['longitude'] ?? $raw['lng'] ?? $raw['x']);
            }
            if (isset($raw['zoom'])) {
                $zoom = $this->NormalizeZoom((int)$raw['zoom']);
            }
            $labelCandidate = trim((string)($raw['label'] ?? $raw['address'] ?? ''));
            if ($labelCandidate !== '') {
                $label = $labelCandidate;
            }
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'zoom' => $zoom,
            'label' => $label
        ];
    }

    private function NormalizeZoom(int $zoom): int
    {
        if ($zoom < 3) {
            return 3;
        }
        if ($zoom > 20) {
            return 20;
        }
        return $zoom;
    }

    private function ReadFloatProperty(int $instanceID, string $property): ?float
    {
        if (!function_exists('IPS_HasProperty') || !IPS_HasProperty($instanceID, $property)) {
            return null;
        }

        $value = IPS_GetProperty($instanceID, $property);
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function ReadStringProperty(int $instanceID, string $property): ?string
    {
        if (!function_exists('IPS_HasProperty') || !IPS_HasProperty($instanceID, $property)) {
            return null;
        }

        $value = IPS_GetProperty($instanceID, $property);
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function ResolveCoordinatesFromInstance(int $instanceID): ?array
    {
        $locationRaw = IPS_GetProperty($instanceID, 'Location');
        if (is_string($locationRaw) && $locationRaw !== '') {
            $decoded = json_decode($locationRaw, true);
            if (is_array($decoded) && isset($decoded['latitude']) && isset($decoded['longitude'])) {
                return [
                    'latitude' => (float)$decoded['latitude'],
                    'longitude' => (float)$decoded['longitude']
                ];
            }
        }
        
        if ($latitude === null || $longitude === null) {
            return null;
        }
    }

    private function GetConfiguredPoints(): array
    {
        $points = json_decode($this->ReadPropertyString('Points'), true);
        if (!is_array($points)) {
            return [];
        }

        $result = [];
        foreach ($points as $index => $point) {
            $latID = (int)($point['LatitudeID'] ?? 0);
            $lonID = (int)($point['LongitudeID'] ?? 0);
            if ($latID <= 0 || $lonID <= 0) {
                continue;
            }
            if (!IPS_VariableExists($latID) || !IPS_VariableExists($lonID)) {
                $this->SendDebug(__FUNCTION__, sprintf('Variable missing for point %d', $index), 0);
                continue;
            }

            $latitude = GetValue($latID);
            $longitude = GetValue($lonID);
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                $this->SendDebug(__FUNCTION__, sprintf('Non numeric values for point %d', $index), 0);
                continue;
            }

            $name = trim((string)($point['Name'] ?? ''));
            if ($name === '') {
                $name = IPS_GetName($latID);
            }

            $icon = $this->NormalizeIconUrl($point['Icon'] ?? '');

            $result[] = [
                'name' => $name,
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
                'icon' => $icon
            ];
        }

        return $result;
    }

    private function NormalizeIconUrl($icon): ?string
    {
        if (!is_string($icon)) {
            return null;
        }

        $trimmed = trim($icon);
        if ($trimmed === '') {
            return null;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['svg', 'png'], true)) {
            return null;
        }

        return $trimmed;
    }

    private function MaintainReferences(): void
    {
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        $locationID = $this->ReadPropertyInteger('LocationControlID');
        if ($locationID > 0) {
            $this->RegisterReference($locationID);
        }

        foreach ($this->GetPointVariableIDs() as $variableID) {
            $this->RegisterReference($variableID);
        }
    }

    private function GetPointVariableIDs(): array
    {
        $ids = [];
        $points = json_decode($this->ReadPropertyString('Points'), true);
        if (!is_array($points)) {
            return $ids;
        }

        foreach ($points as $point) {
            foreach (['LatitudeID', 'LongitudeID'] as $key) {
                $id = (int)($point[$key] ?? 0);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }
}