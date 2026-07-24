# Komfovent Lüftung für Symcon

Moderne Visualisierungskachel für eine über BACnet in Symcon eingebundene Komfovent-Lüftungsanlage.

## Eigenschaften

- modernes Luftstromschema für Außenluft, Zuluft, Abluft und Fortluft
- Anzeige von Temperaturen, Volumenströmen, Ventilatorleistungen und Betriebsart
- funktionale Außen-/Fortluftklappen mit tatsächlichem Öffnungsgrad
- Außen- und Abluftfilter mit Differenzdruck und Wechselwarnung
- zustandsabhängige Anzeige optionaler Heiz-, Kühl-, Befeuchtungs- und Umluftaggregate
- animierte Luftströme und Wärmerückgewinnung
- Alarmanzeige und Erkennung fehlender Daten
- eigenständige BACnet-Aktualisierung im einstellbaren Intervall
- keine doppelten Messvariablen: Vorhandene BACnet-Variablen werden referenziert
- geeignet für die kachelbasierte Visualisierung ab Symcon 7.1

## Einrichtung

Nach dem Anlegen der Instanz werden die auf dem Entwicklungssystem ermittelten BACnet-Variablen vorbelegt. Auf einem anderen System müssen die passenden Quellvariablen einmal in der Instanzkonfiguration ausgewählt werden.

Empfohlen wird eine Kachelgröße von mindestens 3 × 3 Feldern.

## Installation

Die GitHub-Adresse der Bibliothek im Symcon Module Control oder über den Module Store hinzufügen und anschließend eine Instanz **Lüftungsanlage** anlegen.
