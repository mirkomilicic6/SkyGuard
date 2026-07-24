<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('name');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });

        // Approximate town-centre coordinates for each PGP (border police station) —
        // accurate enough for local weather lookups.
        foreach ($this->coordinates() as $name => [$lat, $lon]) {
            DB::table('border_police_stations')
                ->where('name', $name)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }

    public function down(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    private function coordinates(): array
    {
        return [
            // PU Karlovačka
            'PGP Slunj'              => [45.1108, 15.5828],
            'PGP Cetingrad'          => [45.0090, 15.7440],
            'PGP Vojnić'             => [45.3106, 15.6874],
            'PGP Maljevac'           => [45.0206, 15.7935],
            'PGP Rakovica'           => [44.9871, 15.6939],
            'PGP Krnjak'             => [45.2394, 15.7248],

            // PU Sisačko-moslavačka
            'PGP Hrvatska Kostajnica' => [45.2270, 16.5620],
            'PGP Dvor na Uni'         => [45.0645, 16.3730],
            'PGP Donji Kukuruzari'    => [45.1660, 16.4460],
            'PGP Jasenovac'           => [45.2764, 16.9160],
            'PGP Sunja'               => [45.3350, 16.5870],
            'PGP Hrvatska Dubica'     => [45.1840, 16.8110],

            // PU Vukovarsko-srijemska
            'PGP Ilok'      => [45.2119, 19.3719],
            'PGP Tovarnik'  => [45.1595, 19.1520],
            'PGP Lipovac'   => [45.0810, 18.8360],
            'PGP Babska'    => [45.1370, 19.0870],
            'PGP Nijemci'   => [45.0280, 18.9330],
            'PGP Strošinci' => [45.0000, 19.0300],

            // PU Splitsko-dalmatinska
            'PGP Sinj'           => [43.7043, 16.6392],
            'PGP Trilj'          => [43.6167, 16.7167],
            'PGP Imotski'        => [43.4460, 17.2180],
            'PGP Vrgorac'        => [43.2040, 17.3980],
            'PGP Vinjani Gornji' => [43.5150, 17.3000],
            'PGP Kamensko'       => [43.6740, 17.0080],

            // PU Dubrovačko-neretvanska
            'PGP Metković'   => [43.0540, 17.6480],
            'PGP Ploče'      => [43.0530, 17.4340],
            'PGP Osojnik'    => [42.7180, 18.1350],
            'PGP Zaton Doli' => [42.7830, 17.9930],
            'PGP Ivanica'    => [42.6480, 18.3260],
            'PGP Orebić'     => [42.9740, 17.1780],
        ];
    }
};
