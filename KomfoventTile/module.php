<?php

declare(strict_types=1);

class KomfoventLüftungskachel extends IPSModule
{
    private const SOURCES = [
        'OutdoorTemperatureID', 'SupplyTemperatureID', 'ExtractTemperatureID', 'ExhaustTemperatureID',
        'SupplyFlowID', 'ExtractFlowID', 'SupplyFlowSetpointID', 'ExtractFlowSetpointID',
        'SupplyFanID', 'ExtractFanID', 'SupplySetpointID', 'ModeID',
        'HeatPumpID', 'HeatExchangerID', 'AlarmID',
        'OutdoorDamperID', 'ExhaustDamperID',
        'OutdoorFilterID', 'ExtractFilterID', 'OutdoorFilterPressureID', 'ExtractFilterPressureID',
        'OutdoorFilterChangeID', 'ExtractFilterChangeID',
        'WaterHeaterID', 'ElectricHeaterID', 'WaterCoolerID', 'DXUnitID', 'HumidifierID', 'RecirculationID'
    ];

    private const REQUIRED = [
        'OutdoorTemperatureID', 'SupplyTemperatureID', 'ExtractTemperatureID', 'ExhaustTemperatureID',
        'SupplyFlowID', 'ExtractFlowID', 'SupplyFanID', 'ExtractFanID', 'ModeID'
    ];

    public function Create()
    {
        parent::Create();
        $defaults = [
            'OutdoorTemperatureID' => 38072, 'SupplyTemperatureID' => 26199,
            'ExtractTemperatureID' => 33812, 'ExhaustTemperatureID' => 34078,
            'SupplyFlowID' => 30125, 'ExtractFlowID' => 39096,
            'SupplyFlowSetpointID' => 46274, 'ExtractFlowSetpointID' => 32299,
            'SupplyFanID' => 33748, 'ExtractFanID' => 18683,
            'SupplySetpointID' => 43163, 'ModeID' => 22795,
            'HeatPumpID' => 19091, 'HeatExchangerID' => 23267, 'AlarmID' => 37797,
            'OutdoorDamperID' => 24033, 'ExhaustDamperID' => 45079,
            'OutdoorFilterID' => 28103, 'ExtractFilterID' => 43279,
            'OutdoorFilterPressureID' => 34353, 'ExtractFilterPressureID' => 54371,
            'OutdoorFilterChangeID' => 13555, 'ExtractFilterChangeID' => 45375,
            'WaterHeaterID' => 57034, 'ElectricHeaterID' => 57682,
            'WaterCoolerID' => 56649, 'DXUnitID' => 50758,
            'HumidifierID' => 51102, 'RecirculationID' => 11607
        ];
        foreach ($defaults as $name => $id) {
            $this->RegisterPropertyInteger($name, $id);
        }
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterAttributeString('RegisteredSources', '[]');
        $this->RegisterVariableBoolean('DataValid', 'Daten gültig', '~Switch', 10);
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Aktualisierung', '~UnixTimestamp', 20);
        $this->RegisterVariableString('LastError', 'Fehlermeldung', '', 30);
        $this->RegisterTimer('DataUpdate', 0, 'KVL_UpdateData($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->UnregisterSources();
        $missing = [];
        $registered = [];
        foreach (self::SOURCES as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
                $registered[] = $id;
            } elseif (in_array($property, self::REQUIRED, true)) {
                $missing[] = $property;
            }
        }
        $this->WriteAttributeString('RegisteredSources', json_encode(array_values(array_unique($registered))));
        $this->SetTimerInterval('DataUpdate', $missing === [] ? max(15, $this->ReadPropertyInteger('UpdateInterval')) * 1000 : 0);
        if ($missing !== []) {
            $this->SetValue('DataValid', false);
            $this->SetValue('LastError', 'Erforderliche Quellvariablen fehlen');
            $this->SetStatus(201);
            $this->PushTile();
            return;
        }
        $this->UpdateData();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->SetValue('LastUpdate', time());
            $this->SetValue('DataValid', true);
            $this->PushTile();
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident !== 'SetMode') {
            throw new InvalidArgumentException('Unbekannte Aktion: ' . $Ident);
        }
        $mode = (int) $Value;
        if ($mode < 0 || $mode > 5) {
            throw new InvalidArgumentException('Ungültige Betriebsart');
        }
        $variableID = $this->ReadPropertyInteger('ModeID');
        if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
            throw new RuntimeException('BACnet-Betriebsart ist nicht konfiguriert');
        }
        try {
            RequestAction($variableID, (float) $mode);
            IPS_Sleep(400);
            $parent = IPS_GetParent($variableID);
            if ($parent > 0 && IPS_InstanceExists($parent)) {
                BAC_RequestStatus($parent);
            }
            $this->SetValue('LastError', '');
            $this->SetValue('DataValid', true);
            $this->SetValue('LastUpdate', time());
            $this->SetStatus(102);
        } catch (Throwable $e) {
            $this->SetValue('LastError', 'Betriebsart konnte nicht gesetzt werden: ' . $e->getMessage());
            $this->SetStatus(202);
            $this->PushTile();
            throw $e;
        }
        $this->PushTile();
    }

    public function GetVisualizationTile()
    {
        return str_replace('__INITIAL_STATE__', $this->StateJSON(), file_get_contents(__DIR__ . '/module.html'));
    }

    public function UpdateData()
    {
        try {
            $instances = [];
            foreach (self::SOURCES as $property) {
                $id = $this->ReadPropertyInteger($property);
                if ($id <= 0 || !IPS_VariableExists($id)) {
                    continue;
                }
                $parent = IPS_GetParent($id);
                if ($parent > 0 && IPS_InstanceExists($parent)) {
                    $instances[$parent] = true;
                }
            }
            if ($instances === []) {
                throw new RuntimeException('Keine BACnet-Quellen gefunden');
            }
            foreach (array_keys($instances) as $instanceID) {
                try {
                    BAC_RequestStatus($instanceID);
                } catch (Throwable $e) {
                    $this->SendDebug('BACnet', sprintf('%d: %s', $instanceID, $e->getMessage()), 0);
                }
            }
            IPS_Sleep(300);
            foreach (self::REQUIRED as $property) {
                $id = $this->ReadPropertyInteger($property);
                if ($id <= 0 || !IPS_VariableExists($id)) {
                    throw new RuntimeException('Erforderliche BACnet-Variable nicht verfügbar');
                }
            }
            $this->SetValue('DataValid', true);
            $this->SetValue('LastUpdate', time());
            $this->SetValue('LastError', '');
            $this->SetStatus(102);
        } catch (Throwable $e) {
            $this->SetValue('DataValid', false);
            $this->SetValue('LastUpdate', time());
            $this->SetValue('LastError', $e->getMessage());
            $this->SetStatus(202);
            $this->SendDebug('Aktualisierung', $e->getMessage(), 0);
        }
        $this->PushTile();
    }

    private function UnregisterSources(): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredSources'), true);
        if (!is_array($old)) {
            return;
        }
        foreach ($old as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->UnregisterMessage($id, VM_UPDATE);
                $this->UnregisterReference($id);
            }
        }
    }

    private function SourceValue(string $property, $fallback = null)
    {
        $id = $this->ReadPropertyInteger($property);
        return $id > 0 && IPS_VariableExists($id) ? GetValue($id) : $fallback;
    }

    private function StateJSON(): string
    {
        $mode = (int) $this->SourceValue('ModeID', 0);
        $modes = [0 => 'Aus / Standby', 1 => 'Comfort 1', 2 => 'Comfort 2', 3 => 'Economy 1', 4 => 'Economy 2', 5 => 'Special'];
        return (string) json_encode([
            'valid' => (bool) $this->GetValue('DataValid'),
            'outdoor' => (float) $this->SourceValue('OutdoorTemperatureID', 0),
            'supply' => (float) $this->SourceValue('SupplyTemperatureID', 0),
            'extract' => (float) $this->SourceValue('ExtractTemperatureID', 0),
            'exhaust' => (float) $this->SourceValue('ExhaustTemperatureID', 0),
            'supplyFlow' => (float) $this->SourceValue('SupplyFlowID', 0),
            'extractFlow' => (float) $this->SourceValue('ExtractFlowID', 0),
            'supplyFlowSetpoint' => (float) $this->SourceValue('SupplyFlowSetpointID', 0),
            'extractFlowSetpoint' => (float) $this->SourceValue('ExtractFlowSetpointID', 0),
            'supplyFan' => (float) $this->SourceValue('SupplyFanID', 0),
            'extractFan' => (float) $this->SourceValue('ExtractFanID', 0),
            'supplySetpoint' => (float) $this->SourceValue('SupplySetpointID', 0),
            'mode' => $mode,
            'modeName' => $modes[$mode] ?? ('Modus ' . $mode),
            'heatPump' => (float) $this->SourceValue('HeatPumpID', 0),
            'heatExchanger' => (float) $this->SourceValue('HeatExchangerID', 0),
            'outdoorDamper' => (float) $this->SourceValue('OutdoorDamperID', 0),
            'exhaustDamper' => (float) $this->SourceValue('ExhaustDamperID', 0),
            'outdoorFilter' => (bool) $this->SourceValue('OutdoorFilterID', false),
            'extractFilter' => (bool) $this->SourceValue('ExtractFilterID', false),
            'outdoorFilterPressure' => (float) $this->SourceValue('OutdoorFilterPressureID', 0),
            'extractFilterPressure' => (float) $this->SourceValue('ExtractFilterPressureID', 0),
            'outdoorFilterChange' => (bool) $this->SourceValue('OutdoorFilterChangeID', false),
            'extractFilterChange' => (bool) $this->SourceValue('ExtractFilterChangeID', false),
            'waterHeater' => (float) $this->SourceValue('WaterHeaterID', 0),
            'electricHeater' => (float) $this->SourceValue('ElectricHeaterID', 0),
            'waterCooler' => (float) $this->SourceValue('WaterCoolerID', 0),
            'dxUnit' => (float) $this->SourceValue('DXUnitID', 0),
            'humidifier' => (float) $this->SourceValue('HumidifierID', 0),
            'recirculation' => (float) $this->SourceValue('RecirculationID', 0),
            'alarm' => (int) $this->SourceValue('AlarmID', 0),
            'updated' => (int) $this->GetValue('LastUpdate'),
            'error' => (string) $this->GetValue('LastError')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    private function PushTile(): void
    {
        $this->UpdateVisualizationValue($this->StateJSON());
    }
}
