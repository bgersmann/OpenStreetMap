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
- Zeichnet optionale Verlaufs-Trails für Punkte aus einer separaten Track-Liste (Archiv oder String-Variable).
- Bietet in der HTML-Visualisierung eine Navigation, um Verlaufspunkte schrittweise durchzugehen.

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
Verlauf Max Punkte (global) | Obergrenze für Verlaufspunkte je Runde in der HTML-Visualisierung.
Markierungen | Liste von Punkten. Jeder Eintrag besitzt einen Namen sowie zwei Variablen-IDs für Latitude und Longitude.
Verläufe (Tracks) | Separate Liste zur Verlaufskonfiguration. Tracks funktionieren eigenständig (ohne Point-Zuordnung) und können Daten aus Archiv oder einer String-Variable laden.

Zusätzliche Optionen innerhalb der Markierungsliste:

- **Verlauf anzeigen**: Steuert pro Punkt, ob ein Verlauf in der Karte angezeigt wird.
- **Verlauf Minuten**: Bestimmt pro Punkt das Zeitfenster für Archivdaten (letzte X Minuten).

Zusätzliche Optionen innerhalb der Track-Liste:

- **Trackname**: Freie Bezeichnung für die Anzeige im Verlauf-Panel.
- **Archiv Verlauf aktiv**: Aktiviert die Berechnung eines Trails aus den archivierten Latitude/Longitude-Werten.
- **Track (String Variable)**: Variable mit komplettem Track als JSON.
- **Archiv und Track-Einträge**: Jeder Eintrag (Archivwert bzw. JSON-Datensatz) wird als eine Runde interpretiert.
- **Max Punkte**: Zusätzliche Begrenzung pro Track (wird mit dem globalen Limit kombiniert).
- **Zoom berücksichtigen**: Steuert, ob der Marker (inklusive Trail) bei der automatischen Kartenausrichtung mit einbezogen wird.

> Voraussetzung für Archiv-Verläufe: Die Track-Variable muss vom Archive Control geloggt werden.

Die Checkbox **Zoom berücksichtigen** ist zusätzlich in der Liste der festen Punkte vorhanden.

> Hinweis: Ist kein Location Control verknüpft, nutzt das Modul weiterhin die zuvor gespeicherten Koordinaten aus der alten `HouseLocation`-Eigenschaft als Fallback.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Dieses Modul legt keine Statusvariablen und keine eigenen Profile an.

### 6. Visualisierung

Die Visualisierung zeigt die OpenStreetMap-Karte mit einem hervorgehobenen Haus-Marker sowie allen konfigurierten Punkten. Aktivierte Verläufe erscheinen als halbtransparente Polylinien. Über das Verlauf-Panel können je Punkt einzelne Runden ausgewählt und durchgegangen werden.

### 7. PHP-Befehlsreferenz

Derzeit stellt das Modul keine zusätzlichen PHP-Befehle zur Verfügung.