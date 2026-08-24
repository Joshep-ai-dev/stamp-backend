<?php

namespace App\Services;

use Illuminate\Support\Str;
use Locale;
use RuntimeException;

class CountryResolver
{
    private const CONTINENTS = [
        'AF' => 'DZ AO BJ BW BF BI CV CM CF TD KM CG CD CI DJ EG GQ ER SZ ET GA GM GH GN GW KE LS LR LY MG MW ML MR MU YT MA MZ NA NE NG RE RW SH ST SN SC SL SO ZA SS SD TZ TG TN UG EH ZM ZW',
        'AN' => 'AQ BV GS HM TF',
        'AS' => 'AF AM AZ BH BD BT BN KH CN CY GE HK IN ID IR IQ IL JP JO KZ KW KG LA LB MO MY MV MN MM NP KP OM PK PS PH QA SA SG KR LK SY TW TJ TH TL TR TM AE UZ VN YE',
        'EU' => 'AL AD AT BY BE BA BG HR CZ DK EE FO FI FR DE GI GR GG VA HU IS IE IM IT JE LV LI LT LU MT MD MC ME NL MK NO PL PT RO RU SM RS SK SI ES SJ SE CH UA GB AX',
        'NA' => 'AI AG AW BS BB BZ BM BQ CA KY CR CU CW DM DO SV GL GD GP GT HT HN JM MQ MX MS NI PA PR BL KN LC MF PM VC SX TT TC US VG VI',
        'OC' => 'AS AU CX CC CK FJ PF GU KI MH FM NR NC NZ NU NF MP PW PG PN WS SB TK TO TV UM VU WF',
        'SA' => 'AR BO BR CL CO EC FK GF GY PY PE SR UY VE',
    ];

    private ?array $lookup = null;

    public function resolve(string $name): array
    {
        $key = $this->normalize($name);
        $record = $this->lookup()[$key] ?? null;
        if (! $record) {
            throw new RuntimeException("Unknown country: {$name}");
        }

        return $record;
    }

    private function lookup(): array
    {
        if ($this->lookup !== null) {
            return $this->lookup;
        } $map = [];
        foreach (self::CONTINENTS as $continent => $codes) {
            foreach (array_unique(explode(' ', $codes)) as $code) {
                $name = Locale::getDisplayRegion('-'.$code, 'en');
                $map[$this->normalize($name)] = ['code' => $code, 'name' => $name, 'continent_code' => $continent, 'flag' => $this->flag($code)];
            }
        }
        $aliases = [
            'antigua and barbuda' => 'AG', 'bolivia' => 'BO', 'bolivia, plurinational state of' => 'BO',
            'bonaire, sint eustatius and saba' => 'BQ', 'bosnia and herzegovina' => 'BA', 'brunei' => 'BN',
            'brunei darussalam' => 'BN', 'cabo verde' => 'CV', 'cape verde' => 'CV', 'congo' => 'CG',
            'congo, the democratic republic of the' => 'CD', 'czech republic' => 'CZ',
            'democratic republic of the congo' => 'CD', 'east timor' => 'TL', 'falkland islands (malvinas)' => 'FK',
            'holy see (vatican city state)' => 'VA', 'hong kong' => 'HK', 'iran' => 'IR',
            'iran, islamic republic of' => 'IR', 'ivory coast' => 'CI',
            "korea, democratic people's republic of" => 'KP', 'korea, republic of' => 'KR',
            "lao people's democratic republic" => 'LA', 'laos' => 'LA', 'macao' => 'MO', 'mayotte' => 'YT',
            'micronesia, federated states of' => 'FM', 'moldova' => 'MD', 'moldova, republic of' => 'MD',
            'myanmar' => 'MM', 'north korea' => 'KP', 'oman' => 'OM', 'pakistan' => 'PK',
            'palestine, state of' => 'PS', 'palestinian territory' => 'PS', 'pitcairn' => 'PN',
            'republic of the congo' => 'CG', 'russian federation' => 'RU', 'russia' => 'RU', 'reunion' => 'RE',
            'saint barthelemy' => 'BL', 'saint helena, ascension and tristan da cunha' => 'SH',
            'saint kitts and nevis' => 'KN', 'saint lucia' => 'LC', 'saint martin (french part)' => 'MF',
            'saint pierre and miquelon' => 'PM', 'saint vincent and the grenadines' => 'VC',
            'sao tome and principe' => 'ST', 'sint maarten (dutch part)' => 'SX', 'south korea' => 'KR',
            'south georgia and the south sandwich islands' => 'GS', 'svalbard and jan mayen' => 'SJ',
            'swaziland' => 'SZ', 'syrian arab republic' => 'SY', 'syria' => 'SY',
            'taiwan' => 'TW', 'taiwan, province of china' => 'TW', 'tanzania' => 'TZ',
            'tanzania, united republic of' => 'TZ', 'the gambia' => 'GM', 'trinidad and tobago' => 'TT',
            'turks and caicos islands' => 'TC', 'united states' => 'US',
            'venezuela' => 'VE', 'venezuela, bolivarian republic of' => 'VE', 'viet nam' => 'VN',
            'vietnam' => 'VN', 'virgin islands, british' => 'VG', 'virgin islands, u.s.' => 'VI',
            'wallis and futuna' => 'WF',
        ];
        foreach ($aliases as $alias => $code) {
            foreach ($map as $record) {
                if ($record['code'] === $code) {
                    $map[$alias] = $record;
                    break;
                }
            }
        }
        $kosovo = ['code' => 'XK', 'name' => 'Kosovo', 'continent_code' => 'EU', 'flag' => '🇽🇰'];
        $map['kosovo'] = $kosovo;
        $map['no country found for alpha-2 code: xk'] = $kosovo;

        return $this->lookup = $map;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }

    private function flag(string $code): string
    {
        return mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1]));
    }
}
