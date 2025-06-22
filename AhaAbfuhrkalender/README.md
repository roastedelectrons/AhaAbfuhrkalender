# AhaAbfuhrkalender
Modul für Symcon zum Auslesen und Anzeigen der Müllabfuhr-Termine für Restabfall, Papier, Leichtverpackungen und Bioabfall der Region Hannover (aha).

**Verfügbare Städte/Gemeinden:** `Barsinghausen`, `Burgdorf`, `Burgwedel`, `Garbsen`, `Gehrden`, `Hannover`, `Hemmingen`, `Isernhagen`, `Laatzen`, `Langenhagen`, `Lehrte`, `Neustadt a. Rbge.`, `Pattensen`, `Ronnenberg`, `Seelze`, `Sehnde`, `Springe`, `Uetze`, `Wedemark`, `Wennigsen`, `Wunstorf`

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang
* Auslesen der Abfuhrtermine der Region Hannover
* Anzeige der Abfuhrtermine als Variabke
* Anezige der Tage bis zur nächsten Abfuhr als Variable
* Bereitstellung der Termine als iCal Link (z.B. zur Verwendung der Darstellungen des Modul Abfallwirtschaft)

## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung|
|-----| -----| -----| 
|City | Select | Gemeinde |
|StreetsFirstLetter | Select | Anfangsbuchstabe der Strasse |
|Street | Select | Strasse |
|HouseNumber | ValidationTextBox | Hausnummer |
|HouseNumberAddon | ValidationTextBox | Hausnummerzusatz |
|VariableTimestamp | CheckBox | Variablen für Datum der nächsten Abholung anlegen |
|VariableDays | CheckBox | Variablen für Tage bis zur nächsten Abholung anlegen |
|SortVariables | CheckBox | Sortiere Variablen nach Datum der nächsten Abholung |
|EnableWebHook | CheckBox | Termine als iCal-Datei über WebHook bereitstellen (z.B. für Modul Abfallwirtschaft) |

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|Restabfall_Timestamp |int |~UnixTimestampDate |Restabfall |
|Restabfall_Days |int |AhaAbfuhrkalender.Days |Restabfall |
|Bioabfall_Timestamp |int |~UnixTimestampDate |Bioabfall |
|Bioabfall_Days |int |AhaAbfuhrkalender.Days |Bioabfall |
|Papier_Timestamp |int |~UnixTimestampDate |Papier |
|Papier_Days |int |AhaAbfuhrkalender.Days |Papier |
|Leichtverpackungen_Timestamp |int |~UnixTimestampDate |Leichtverpackungen |
|Leichtverpackungen_Days |int |AhaAbfuhrkalender.Days |Leichtverpackungen |

## PHP-Befehlsreferenz

### GetDates
```php
AHA_GetDates( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AhaAbfuhrkalender-Instanz |

### GetICAL
```php
AHA_GetICAL( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AhaAbfuhrkalender-Instanz |

### GetIcalLink
```php
AHA_GetIcalLink( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AhaAbfuhrkalender-Instanz |

### RefreshAccessToken
```php
AHA_RefreshAccessToken( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AhaAbfuhrkalender-Instanz |

### Update
```php
AHA_Update( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AhaAbfuhrkalender-Instanz |
