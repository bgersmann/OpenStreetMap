# OpenStreetMap
Diese Modul stellt verschiedene Erweiterungen bereit, um die Arbeit mit Symcon zu vereinfachen.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Visualisiert eine OpenStreetMap-Karte direkt im WebFront mittels Leaflet.
- Nutzt das Symcon Location Control, um die Hausposition auszulesen und als eigenen Marker darzustellen.
- Bindet beliebige Punkte auf der Karte ein, deren Koordinaten aus Variablen (Latitude/Longitude) stammen.
- Aktualisiert Beschriftungen und Markeranzahl in einer Info-Karte innerhalb der Visualisierung.
- Zeichnet auf Wunsch einen Verlaufs-Trail für einzelne Punkte und nutzt dafür die archivierten Koordinatenwerte.

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1

### 3. Software-Installation

* Über den Module Store das 'OpenStreetMap'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'OpenStreetMap'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Location Control | Auswahl der Kern-Instanz *Location* (Kern Instanzen -> Location). Deren Koordinaten werden direkt übernommen.
Zoomstufe | Optionaler Standard-Zoom für die Kartenansicht, falls das Location Control keinen Kartenmaßstab vorgibt.
Markierungen | Liste von Punkten. Jeder Eintrag besitzt einen Namen sowie zwei Variablen-IDs für Latitude und Longitude.

Zusätzliche Optionen innerhalb der Markierungsliste:

- **Verlauf aktiv**: Aktiviert die Berechnung eines Trails für diesen Punkt.
- **Dauer (Minuten)**: Bestimmt, wie weit in die Vergangenheit Koordinaten aus dem Archiv berücksichtigt werden (5–1440 Minuten).
- **Max Punkte**: Begrenzt die Anzahl der Linienpunkte zur Schonung von Browser und Netzwerk.
- **Zoom berücksichtigen**: Steuert, ob der Marker (inklusive Trail) bei der automatischen Kartenausrichtung mit einbezogen wird.

> Voraussetzung für den Verlauf: Beide Positions-Variablen müssen vom Archive Control geloggt werden.

Die Checkbox **Zoom berücksichtigen** ist zusätzlich in der Liste der festen Punkte vorhanden.

> Hinweis: Ist kein Location Control verknüpft, nutzt das Modul weiterhin die zuvor gespeicherten Koordinaten aus der alten `HouseLocation`-Eigenschaft als Fallback.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Dieses Modul legt keine Statusvariablen und keine eigenen Profile an.

### 6. Visualisierung

Die Visualisierung zeigt die OpenStreetMap-Karte mit einem hervorgehobenen Haus-Marker sowie allen konfigurierten Punkten. Ein Info-Panel blendet die Hausbezeichnung und die Anzahl der Marker ein. Aktivierte Verläufe erscheinen als halbtransparente Polylinien, die die zuletzt archivierten Positionen verbinden.

### 7. PHP-Befehlsreferenz

Derzeit stellt das Modul keine zusätzlichen PHP-Befehle zur Verfügung.