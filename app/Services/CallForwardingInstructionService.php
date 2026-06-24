<?php

namespace App\Services;

use InvalidArgumentException;

class CallForwardingInstructionService
{
    public const MODES = ['no_answer', 'always', 'busy_unreachable', 'outside_hours', 'operator_help'];

    private const COUNTRY_NAMES = [
        'US' => 'Estados Unidos', 'CA' => 'Canadá', 'GB' => 'Reino Unido',
        'ES' => 'España', 'MX' => 'México', 'CO' => 'Colombia',
    ];

    private const COUNTRY_PREFIXES = [
        '34' => 'ES', '44' => 'GB', '52' => 'MX', '57' => 'CO', '1' => 'US',
    ];

    public function countryForPhone(?string $phone, ?string $savedCountry = null): string
    {
        $saved = strtoupper((string) $savedCountry);
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        // +1 se comparte entre EE. UU. y Canadá; el país guardado evita adivinar.
        if (str_starts_with($digits, '1') && in_array($saved, ['US', 'CA'], true)) {
            return $saved;
        }

        foreach (self::COUNTRY_PREFIXES as $prefix => $country) {
            if (str_starts_with($digits, $prefix)) {
                return $country;
            }
        }

        return array_key_exists($saved, self::COUNTRY_NAMES) ? $saved : 'OTHER';
    }

    public function countryName(string $country): string
    {
        return self::COUNTRY_NAMES[strtoupper($country)] ?? 'País no identificado';
    }

    public function operators(string $country): array
    {
        return match (strtoupper($country)) {
            'US' => ['att' => 'AT&T', 'tmobile' => 'T-Mobile', 'verizon' => 'Verizon', 'other' => 'Otro operador'],
            'CA' => ['bell' => 'Bell', 'rogers' => 'Rogers', 'telus' => 'Telus', 'other' => 'Otro operador'],
            'ES' => ['movistar' => 'Movistar', 'vodafone' => 'Vodafone', 'orange' => 'Orange', 'masmovil' => 'MásMóvil / Yoigo', 'other' => 'Otro operador'],
            'GB' => ['ee' => 'EE', 'o2' => 'O2', 'vodafone' => 'Vodafone', 'three' => 'Three', 'other' => 'Otro operador'],
            'MX' => ['telcel' => 'Telcel', 'att' => 'AT&T México', 'movistar' => 'Movistar', 'other' => 'Otro operador'],
            'CO' => ['claro' => 'Claro', 'movistar' => 'Movistar', 'tigo' => 'Tigo', 'wom' => 'WOM', 'other' => 'Otro operador'],
            default => ['other' => 'Mi operador'],
        };
    }

    public function message(string $mode, string $secretaryNumber, int $ringSeconds = 20, string $country = 'ES', string $operator = 'other'): string
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Modo de desvío no válido.');
        }

        $country = strtoupper($country);
        $operators = $this->operators($country);
        if (! array_key_exists($operator, $operators)) {
            throw new InvalidArgumentException('Operador no válido para el país seleccionado.');
        }

        $number = $this->phoneForCode($secretaryNumber);
        $seconds = min(30, max(15, $ringSeconds));
        $operatorName = $operators[$operator];
        $prefix = "Secretary365 - {$this->countryName($country)}, {$operatorName}. ";
        $suffix = ' Si da error, no repitas el código: usa Ajustes > Llamadas > Desvío o consulta a tu operador. Puede tener coste.';

        if ($mode === 'operator_help' || $operator === 'other' || $country === 'CA' || in_array($country, ['MX', 'CO'], true)) {
            return $prefix."Número de destino: {$number}. Pide a tu operador activar el desvío ".$this->modeRequest($mode, $seconds).'.'.$suffix;
        }

        if ($country === 'US' && $operator === 'verizon') {
            $instructions = match ($mode) {
                'always' => "Todas las llamadas: marca *72{$number}. Para quitarlo: *73.",
                'no_answer', 'busy_unreachable' => "Si no respondes o la línea está ocupada: marca *71{$number}. Para quitarlo, usa la configuración de desvío de Verizon.",
                'outside_hours' => "Al cerrar marca *72{$number}. Al abrir marca *73. Repite cada día.",
                default => "Número de destino: {$number}.",
            };
            return $prefix.$instructions.$suffix;
        }

        // Perfiles GSM compatibles: España, Reino Unido, AT&T y T-Mobile.
        $instructions = match ($mode) {
            'no_answer' => "Tras {$seconds}s sin responder: **61*{$number}**{$seconds}#. Para quitarlo: ##61#.",
            'always' => "Todas las llamadas: **21*{$number}#. Para quitarlo: ##21#.",
            'busy_unreachable' => "Si está ocupado: **67*{$number}#. Apagado o sin cobertura: **62*{$number}#. Para quitar: ##67# y ##62#.",
            'outside_hours' => "Al cerrar: **21*{$number}#. Al abrir: ##21#. Repite cada día; la programación depende del operador.",
            default => "Número de destino: {$number}.",
        };

        return $prefix.$instructions.$suffix;
    }

    public function label(string $mode, int $ringSeconds = 20): string
    {
        return match ($mode) {
            'no_answer' => "Después de {$ringSeconds} segundos sin contestar",
            'always' => 'Todas las llamadas',
            'busy_unreachable' => 'Cuando esté ocupado o sin cobertura',
            'outside_hours' => 'Fuera de horario, activación manual',
            'operator_help' => 'Ayuda del operador',
            default => 'Sin configurar',
        };
    }

    private function modeRequest(string $mode, int $seconds): string
    {
        return match ($mode) {
            'always' => 'para todas las llamadas',
            'no_answer' => "cuando no respondas tras {$seconds} segundos",
            'busy_unreachable' => 'cuando comunique, esté apagado o sin cobertura',
            'outside_hours' => 'fuera del horario del negocio',
            default => 'con la condición que prefieras',
        };
    }

    private function phoneForCode(string $phone): string
    {
        $prefix = str_starts_with(trim($phone), '+') ? '+' : '';
        return $prefix.(preg_replace('/\D+/', '', $phone) ?: '');
    }
}
