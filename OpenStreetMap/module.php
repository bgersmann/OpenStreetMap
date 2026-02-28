<?php

declare(strict_types=1);

// CLASS OpenStreetMap
class OpenStreetMap extends IPSModule
{
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const DEFAULT_TRAIL_MINUTES = 60;
    private const DEFAULT_TRAIL_POINTS = 200;

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
        $this->RegisterPropertyString('Tracks', '[]');
        $this->RegisterPropertyString('FixedPoints', '[]');
        $this->RegisterPropertyInteger('GlobalTrailMaxPoints', self::DEFAULT_TRAIL_POINTS);
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
            'points' => $this->GetAllPoints()
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

        $latitude = null;
        foreach (['Latitude', 'latitude', 'Lat', 'lat', 'Y', 'y'] as $property) {
            $latitude = $this->ReadFloatProperty($instanceID, $property);
            if ($latitude !== null) {
                break;
            }
        }

        $longitude = null;
        foreach (['Longitude', 'longitude', 'Lon', 'lon', 'Lng', 'lng', 'X', 'x'] as $property) {
            $longitude = $this->ReadFloatProperty($instanceID, $property);
            if ($longitude !== null) {
                break;
            }
        }

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
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
            $latitude = null;
            $longitude = null;

            if ($latID > 0 && $lonID > 0) {
                if (!IPS_VariableExists($latID) || !IPS_VariableExists($lonID)) {
                    $this->SendDebug(__FUNCTION__, sprintf('Variable missing for point %d', $index), 0);
                } else {
                    $latitudeValue = GetValue($latID);
                    $longitudeValue = GetValue($lonID);
                    if (!is_numeric($latitudeValue) || !is_numeric($longitudeValue)) {
                        $this->SendDebug(__FUNCTION__, sprintf('Non numeric values for point %d', $index), 0);
                    } else {
                        $latitude = (float)$latitudeValue;
                        $longitude = (float)$longitudeValue;
                    }
                }
            }

            $name = $this->ResolvePointName($point, $index, $latID);

            $icon = $this->NormalizeIconUrl($point['Icon'] ?? '');

            $result[] = [
                'name' => $name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'icon' => $icon,
                'trail' => [],
                'trailRounds' => [],
                'includeInZoom' => array_key_exists('IncludeInZoom', $point) ? (bool)$point['IncludeInZoom'] : true,
                'showTrail' => array_key_exists('ShowTrail', $point) ? (bool)$point['ShowTrail'] : true,
                'trailMinutes' => $this->NormalizeTrailMinutes($point['TrailMinutes'] ?? null),
                '__nameKey' => $this->NormalizePointName($name),
                '__latitudeID' => $latID,
                '__longitudeID' => $lonID,
                '__index' => $index
            ];
        }

        return $result;
    }

    private function GetAllPoints(): array
    {
        $configuredPoints = $this->ApplyConfiguredTracks($this->GetConfiguredPoints());
        $preparedConfigured = [];

        foreach ($configuredPoints as $point) {
            $latitude = $point['latitude'];
            $longitude = $point['longitude'];
            $trailCoordinates = is_array($point['trail']) ? $point['trail'] : [];

            if (($latitude === null || $longitude === null) && $trailCoordinates !== []) {
                $lastTrailPoint = $trailCoordinates[count($trailCoordinates) - 1];
                $latitude = (float)$lastTrailPoint['latitude'];
                $longitude = (float)$lastTrailPoint['longitude'];
            }

            if ($latitude === null || $longitude === null) {
                $this->SendDebug(__FUNCTION__, sprintf('Skipping point "%s" because no coordinates are available', (string)$point['name']), 0);
                continue;
            }

            $entry = [
                'name' => $point['name'],
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
                'icon' => $point['icon'],
                'includeInZoom' => (bool)$point['includeInZoom']
            ];

            if ($trailCoordinates !== []) {
                $entry['trail'] = $trailCoordinates;
            }

            if (isset($point['trailRounds']) && is_array($point['trailRounds']) && $point['trailRounds'] !== []) {
                $entry['trailRounds'] = $point['trailRounds'];
            }

            $preparedConfigured[] = $entry;
        }

        $standaloneTracks = $this->GetStandaloneTrackPoints($configuredPoints);

        return array_merge($this->GetFixedPoints(), $preparedConfigured, $standaloneTracks);
    }

    private function GetStandaloneTrackPoints(array $configuredPoints): array
    {
        $tracks = $this->GetTrackConfigurations();
        if ($tracks === []) {
            return [];
        }

        $nameKeysOfConfiguredPoints = [];
        foreach ($configuredPoints as $point) {
            $nameKey = (string)($point['__nameKey'] ?? '');
            if ($nameKey !== '') {
                $nameKeysOfConfiguredPoints[$nameKey] = true;
            }
        }

        $archiveID = null;
        $archiveLookupDone = false;
        $archiveWarningIssued = false;
        $result = [];

        foreach ($tracks as $trackIndex => $track) {
            if (!is_array($track)) {
                continue;
            }

            $trackName = $this->ResolveTrackDisplayName($track, $trackIndex);
            $pointNameKey = $this->NormalizePointName(trim((string)($track['PointName'] ?? '')));

            if ($pointNameKey !== '' && array_key_exists($pointNameKey, $nameKeysOfConfiguredPoints)) {
                continue;
            }

            $trackVariableID = (int)($track['TrackVariableID'] ?? 0);
            if ($trackVariableID <= 0) {
                continue;
            }

            if (!IPS_VariableExists($trackVariableID)) {
                $this->SendDebug(__FUNCTION__, sprintf('Track variable %d missing for standalone track "%s"', $trackVariableID, $trackName), 0);
                continue;
            }

            $trailMaxPoints = $this->ResolveTrailMaxPoints($track['TrackMaxPoints'] ?? null);
            $trailRounds = [];

            if ((bool)($track['ArchiveEnabled'] ?? true)) {
                if (!$archiveLookupDone) {
                    $archiveID = $this->GetArchiveControlID();
                    $archiveLookupDone = true;
                }

                if ($archiveID === null) {
                    if (!$archiveWarningIssued) {
                        $this->SendDebug(__FUNCTION__, 'Archive Control instance missing. Standalone tracks cannot use archive.', 0);
                        $archiveWarningIssued = true;
                    }
                } else {
                    $trailMinutes = $this->NormalizeTrailMinutes($track['TrackMinutes'] ?? null);
                    $trailRounds = $this->BuildTrailRoundsFromArchive($archiveID, $trackVariableID, $trailMinutes, $trailMaxPoints);
                }
            }

            if ($trailRounds === []) {
                $singleRound = $this->BuildTrackRoundFromVariable($trackVariableID, $trailMaxPoints);
                if ($singleRound !== []) {
                    $trailRounds = [$singleRound];
                }
            }

            if ($trailRounds === []) {
                continue;
            }

            $latestRound = $trailRounds[count($trailRounds) - 1];
            $lastPoint = $latestRound[count($latestRound) - 1];

            $entry = [
                'name' => $trackName,
                'latitude' => (float)$lastPoint['latitude'],
                'longitude' => (float)$lastPoint['longitude'],
                'icon' => null,
                'includeInZoom' => true,
                'trail' => $latestRound,
                'trailRounds' => $trailRounds
            ];

            $result[] = $entry;
        }

        return $result;
    }

    private function ApplyConfiguredTracks(array $configuredPoints): array
    {
        if ($configuredPoints === []) {
            return $configuredPoints;
        }

        $tracks = $this->GetTrackConfigurations();
        if ($tracks === []) {
            return $configuredPoints;
        }

        $indexByName = [];
        foreach ($configuredPoints as $index => $point) {
            $nameKey = (string)($point['__nameKey'] ?? '');
            if ($nameKey === '' || array_key_exists($nameKey, $indexByName)) {
                continue;
            }
            $indexByName[$nameKey] = $index;
        }

        $archiveID = null;
        $archiveLookupDone = false;
        $archiveWarningIssued = false;

        foreach ($tracks as $trackIndex => $track) {
            if (!is_array($track)) {
                continue;
            }

            $pointName = trim((string)($track['PointName'] ?? ''));
            $pointIndex = null;

            if ($pointName !== '') {
                $pointNameKey = $this->NormalizePointName($pointName);
                if (array_key_exists($pointNameKey, $indexByName)) {
                    $pointIndex = $indexByName[$pointNameKey];
                } elseif (count($configuredPoints) === 1) {
                    $pointIndex = array_key_first($configuredPoints);
                    $this->SendDebug(__FUNCTION__, sprintf('Track target "%s" does not match exactly, fallback to the only point "%s"', $pointName, (string)$configuredPoints[$pointIndex]['name']), 0);
                } else {
                    $showTrailCandidates = [];
                    foreach ($configuredPoints as $candidateIndex => $candidatePoint) {
                        if ((bool)($candidatePoint['showTrail'] ?? true)) {
                            $showTrailCandidates[] = $candidateIndex;
                        }
                    }

                    if (count($showTrailCandidates) === 1) {
                        $pointIndex = $showTrailCandidates[0];
                        $this->SendDebug(__FUNCTION__, sprintf('Track target "%s" fallback to single trail-enabled point "%s"', $pointName, (string)$configuredPoints[$pointIndex]['name']), 0);
                    } else {
                        $this->SendDebug(__FUNCTION__, sprintf('Track target "%s" does not match any configured point', $pointName), 0);
                        continue;
                    }
                }
            } else {
                continue;
            }

            if (!(bool)($configuredPoints[$pointIndex]['showTrail'] ?? true)) {
                continue;
            }

            $trackVariableID = (int)($track['TrackVariableID'] ?? 0);
            $trailRounds = [];
            $trailMaxPoints = $this->ResolveTrailMaxPoints($track['TrackMaxPoints'] ?? null);

            if ($trackVariableID > 0) {
                if (!IPS_VariableExists($trackVariableID)) {
                    $this->SendDebug(__FUNCTION__, sprintf('Track variable %d missing for point "%s"', $trackVariableID, $pointName), 0);
                } else {
                    if ((bool)($track['ArchiveEnabled'] ?? true)) {
                        if (!$archiveLookupDone) {
                            $archiveID = $this->GetArchiveControlID();
                            $archiveLookupDone = true;
                        }

                        if ($archiveID === null) {
                            if (!$archiveWarningIssued) {
                                $this->SendDebug(__FUNCTION__, 'Archive Control instance missing. Trails cannot be generated.', 0);
                                $archiveWarningIssued = true;
                            }
                            continue;
                        }

                        $trailMinutes = $this->NormalizeTrailMinutes($configuredPoints[$pointIndex]['trailMinutes'] ?? self::DEFAULT_TRAIL_MINUTES);
                        $trailRounds = $this->BuildTrailRoundsFromArchive($archiveID, $trackVariableID, $trailMinutes, $trailMaxPoints);

                        if ($trailRounds === []) {
                            $singleRound = $this->BuildTrackRoundFromVariable($trackVariableID, $trailMaxPoints);
                            if ($singleRound !== []) {
                                $trailRounds = [$singleRound];
                            }
                        }
                    } else {
                        $singleRound = $this->BuildTrackRoundFromVariable($trackVariableID, $trailMaxPoints);
                        if ($singleRound !== []) {
                            $trailRounds = [$singleRound];
                        }
                    }
                }
            }

            if ($trailRounds !== []) {
                $configuredPoints[$pointIndex]['trailRounds'] = $trailRounds;
                $configuredPoints[$pointIndex]['trail'] = $trailRounds[count($trailRounds) - 1];
            }
        }

        return $configuredPoints;
    }

    private function GetTrackConfigurations(): array
    {
        $tracks = json_decode($this->ReadPropertyString('Tracks'), true);
        if (!is_array($tracks)) {
            $tracks = [];
        }

        return array_merge($tracks, $this->GetLegacyTrackConfigurations());
    }

    private function GetLegacyTrackConfigurations(): array
    {
        $points = json_decode($this->ReadPropertyString('Points'), true);
        if (!is_array($points)) {
            return [];
        }

        $legacyTracks = [];
        foreach ($points as $index => $point) {
            if (!is_array($point)) {
                continue;
            }

            $trackVariableID = (int)($point['TrackVariableID'] ?? 0);
            $archiveEnabled = (bool)($point['TrackEnabled'] ?? false);
            if (!$archiveEnabled && $trackVariableID <= 0) {
                continue;
            }

            $latID = (int)($point['LatitudeID'] ?? 0);
            $pointName = $this->ResolvePointName($point, $index, $latID);
            $legacyTracks[] = [
                'PointName' => $pointName,
                'ArchiveEnabled' => $archiveEnabled,
                'TrackVariableID' => $trackVariableID,
                'TrackMaxPoints' => $point['TrackMaxPoints'] ?? null
            ];
        }

        return $legacyTracks;
    }

    private function ResolveTrackDisplayName(array $track, int $trackIndex): string
    {
        $name = trim((string)($track['Name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $pointName = trim((string)($track['PointName'] ?? ''));
        if ($pointName !== '') {
            return $pointName;
        }

        return sprintf('Track %d', $trackIndex + 1);
    }

    private function ResolvePointName(array $point, int $index, int $latitudeID): string
    {
        $name = trim((string)($point['Name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        if ($latitudeID > 0 && IPS_VariableExists($latitudeID)) {
            return IPS_GetName($latitudeID);
        }

        return sprintf('Punkt %d', $index + 1);
    }

    private function NormalizePointName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function GetFixedPoints(): array
    {
        $points = json_decode($this->ReadPropertyString('FixedPoints'), true);
        if (!is_array($points)) {
            return [];
        }

        $result = [];
        foreach ($points as $index => $point) {
            if (!is_array($point)) {
                continue;
            }

            $latitude = $this->NormalizeCoordinate($point['Latitude'] ?? null);
            $longitude = $this->NormalizeCoordinate($point['Longitude'] ?? null);

            if ($latitude === null || $longitude === null) {
                $this->SendDebug(__FUNCTION__, sprintf('Skipping fixed point %d due to invalid coordinates', $index), 0);
                continue;
            }

            $name = trim((string)($point['Name'] ?? ''));
            if ($name === '') {
                $name = sprintf('Fester Punkt %d', $index + 1);
            }

            $icon = $this->NormalizeIconUrl($point['Icon'] ?? '');

            $result[] = [
                'name' => $name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'icon' => $icon,
                'includeInZoom' => array_key_exists('IncludeInZoom', $point) ? (bool)$point['IncludeInZoom'] : true
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

    private function NormalizeCoordinate($value): ?float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
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

        foreach ($this->GetTrackConfigurations() as $track) {
            if (!is_array($track)) {
                continue;
            }

            $trackVariableID = (int)($track['TrackVariableID'] ?? 0);
            if ($trackVariableID > 0) {
                $ids[$trackVariableID] = true;
            }
        }

        return array_keys($ids);
    }

    private function GetArchiveControlID(): ?int
    {
        if (!function_exists('IPS_GetInstanceListByModuleID')) {
            return null;
        }

        $instances = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE_GUID);
        foreach ($instances as $instanceID) {
            if (IPS_InstanceExists($instanceID)) {
                return $instanceID;
            }
        }

        return null;
    }

    private function BuildTrailRoundsFromArchive(int $archiveID, int $trackVariableID, int $durationMinutes, int $maxPoints): array
    {
        if (!function_exists('AC_GetLoggedValues')) {
            return [];
        }

        $endTime = time();
        $startTime = $endTime - ($durationMinutes * 60);

        try {
            $values = AC_GetLoggedValues($archiveID, $trackVariableID, $startTime, $endTime, 0);
        } catch (\Throwable $exception) {
            $this->SendDebug(__FUNCTION__, sprintf('Archive query failed: %s', $exception->getMessage()), 0);
            return [];
        }

        if (!is_array($values) || $values === []) {
            return [];
        }

        $rounds = [];
        foreach ($values as $entry) {
            if (!isset($entry['TimeStamp']) || !isset($entry['Value'])) {
                continue;
            }

            $round = $this->ParseTrackRound($entry['Value'], (int)$entry['TimeStamp'], $maxPoints);
            if ($round !== []) {
                $rounds[] = $round;
            }
        }

        if ($rounds === []) {
            return [];
        }

        return array_values($rounds);
    }

    private function BuildTrackRoundFromVariable(int $variableID, int $maxPoints): array
    {
        if (!IPS_VariableExists($variableID)) {
            return [];
        }

        $rawValue = GetValue($variableID);
        if (is_string($rawValue)) {
            $rawValue = trim($rawValue);
        }

        if ($rawValue === '' || $rawValue === null) {
            return [];
        }

        $round = $this->ParseTrackRound($rawValue, time(), $maxPoints);
        if ($round === []) {
            $this->SendDebug(__FUNCTION__, sprintf('Invalid track payload in variable %d', $variableID), 0);
            return [];
        }

        return $round;
    }

    private function ParseTrackRound($rawTrack, int $fallbackTimestamp, int $maxPoints): array
    {
        $decoded = null;
        if (is_array($rawTrack)) {
            $decoded = $rawTrack;
        } elseif (is_string($rawTrack)) {
            $trimmed = trim($rawTrack);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (!is_array($decoded)) {
                return [];
            }
        } else {
            return [];
        }

        $trackEntries = $this->ExtractTrackEntries($decoded);
        if ($trackEntries === []) {
            return [];
        }

        $round = [];
        foreach ($trackEntries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $latitude = $this->ExtractCoordinateFromTrackEntry($entry, ['latitude', 'lat', 'y', 1]);
            $longitude = $this->ExtractCoordinateFromTrackEntry($entry, ['longitude', 'lon', 'lng', 'x', 0]);
            if ($latitude === null || $longitude === null) {
                continue;
            }

            $timeCandidate = $entry['timestamp'] ?? $entry['time'] ?? $entry['date'] ?? $entry['t'] ?? $entry['ts'] ?? $fallbackTimestamp;
            $round[] = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => $this->NormalizeTrackTimestamp($timeCandidate, $fallbackTimestamp + $index)
            ];
        }

        if (count($round) <= 1) {
            return [];
        }

        return $this->LimitTrailPoints($round, $maxPoints);
    }

    private function ExtractTrackEntries(array $decoded): array
    {
        if (isset($decoded['type']) && $decoded['type'] === 'LineString' && isset($decoded['coordinates']) && is_array($decoded['coordinates'])) {
            return $decoded['coordinates'];
        }

        $keys = ['track', 'trail', 'points', 'coordinates', 'path'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $decoded) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if (array_key_exists(0, $decoded)) {
            return $decoded;
        }

        return [];
    }

    private function ExtractCoordinateFromTrackEntry(array $entry, array $candidates): ?float
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $entry)) {
                return $this->NormalizeCoordinate($entry[$candidate]);
            }
        }

        return null;
    }

    private function NormalizeTrackTimestamp($value, int $fallback): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $parsed = strtotime($trimmed);
                if ($parsed !== false) {
                    return $parsed;
                }
            }
        }

        return $fallback;
    }

    private function LimitTrailPoints(array $trail, int $maxPoints): array
    {
        if ($maxPoints <= 0) {
            return $trail;
        }

        $total = count($trail);
        if ($total <= $maxPoints) {
            return $trail;
        }

        $step = max(1, (int)floor($total / $maxPoints));
        $filtered = [];
        for ($i = 0; $i < $total; $i += $step) {
            $filtered[] = $trail[$i];
        }

        $lastTrailPoint = $trail[$total - 1];
        if ($filtered === [] || $filtered[count($filtered) - 1]['timestamp'] !== $lastTrailPoint['timestamp']) {
            $filtered[] = $lastTrailPoint;
        }

        if (count($filtered) > $maxPoints) {
            $filtered = array_slice($filtered, -$maxPoints);
        }

        return array_values($filtered);
    }

    private function NormalizeTrailMinutes($value): int
    {
        $minutes = (int)$value;
        if ($minutes <= 0) {
            $minutes = self::DEFAULT_TRAIL_MINUTES;
        }

        if ($minutes < 5) {
            return 5;
        }

        if ($minutes > 1440) {
            return 1440;
        }

        return $minutes;
    }

    private function NormalizeTrailMaxPoints($value): int
    {
        $points = (int)$value;
        if ($points <= 0) {
            $points = self::DEFAULT_TRAIL_POINTS;
        }

        if ($points < 20) {
            return 20;
        }

        if ($points > 500) {
            return 500;
        }

        return $points;
    }

    private function NormalizeGlobalTrailMaxPoints($value): int
    {
        $points = (int)$value;
        if ($points <= 0) {
            $points = self::DEFAULT_TRAIL_POINTS;
        }

        if ($points < 20) {
            return 20;
        }

        if ($points > 1000) {
            return 1000;
        }

        return $points;
    }

    private function ResolveTrailMaxPoints($value): int
    {
        $localMax = $this->NormalizeTrailMaxPoints($value);
        $globalMax = $this->NormalizeGlobalTrailMaxPoints($this->ReadPropertyInteger('GlobalTrailMaxPoints'));

        return min($localMax, $globalMax);
    }
}