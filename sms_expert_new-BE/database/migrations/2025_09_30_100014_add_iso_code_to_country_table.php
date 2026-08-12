<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country', function (Blueprint $table) {
            if (!Schema::hasColumn('country', 'iso_code')) {
                $table->string('iso_code', 3)->nullable()->after('name')->index();
            }
             if (!Schema::hasColumn('country', 'cost_per_sms')) {
                $table->decimal('cost_per_sms', 8,4)->nullable()->after('iso_code')->index();
            }
        });

        // ISO 3166-1 alpha-2 codes mapped by country.id
        $isoCodes = [
            ['id' => 228, 'iso_code' => 'KW'], // Kuwait
            ['id' => 2, 'iso_code' => 'CA'],   // Canada
            ['id' => 3, 'iso_code' => 'US'],   // USA
            ['id' => 4, 'iso_code' => 'BS'],   // Bahamas
            ['id' => 5, 'iso_code' => 'BB'],   // Barbados
            ['id' => 6, 'iso_code' => 'AI'],   // Anguilla
            ['id' => 7, 'iso_code' => 'AG'],   // Antigua and Barbuda
            ['id' => 8, 'iso_code' => 'VG'],   // Virgin Islands (U.K.)
            ['id' => 9, 'iso_code' => 'VI'],   // Virgin Islands (U.S.)
            ['id' => 10, 'iso_code' => 'KY'],  // Cayman Islands
            ['id' => 11, 'iso_code' => 'BM'],  // Bermuda
            ['id' => 12, 'iso_code' => 'GD'],  // Grenada
            ['id' => 13, 'iso_code' => 'TC'],  // Turks and Caicos Islands
            ['id' => 14, 'iso_code' => 'MS'],  // Montserrat
            ['id' => 15, 'iso_code' => 'MP'],  // Northern Marianas
            ['id' => 16, 'iso_code' => 'GU'],  // Guam
            ['id' => 17, 'iso_code' => 'LC'],  // St. Lucia
            ['id' => 18, 'iso_code' => 'DM'],  // Dominica
            ['id' => 19, 'iso_code' => 'VC'],  // St. Vincent and Grenadines
            ['id' => 20, 'iso_code' => 'PR'],  // Puerto Rico
            ['id' => 21, 'iso_code' => 'DO'],  // Dominican Republic
            ['id' => 22, 'iso_code' => 'TT'],  // Trinidad and Tobago
            ['id' => 23, 'iso_code' => 'KN'],  // St. Kitts and Nevis
            ['id' => 24, 'iso_code' => 'JM'],  // Jamaica
            ['id' => 25, 'iso_code' => 'EG'],  // Egypt
            ['id' => 26, 'iso_code' => 'MA'],  // Morocco
            ['id' => 27, 'iso_code' => 'DZ'],  // Algeria
            ['id' => 28, 'iso_code' => 'TN'],  // Tunisia
            ['id' => 29, 'iso_code' => 'LY'],  // Libya
            ['id' => 30, 'iso_code' => 'GM'],  // Gambia
            ['id' => 31, 'iso_code' => 'SN'],  // Senegal
            ['id' => 32, 'iso_code' => 'MR'],  // Mauritania
            ['id' => 33, 'iso_code' => 'ML'],  // Mali
            ['id' => 34, 'iso_code' => 'GN'],  // Guinea
            ['id' => 35, 'iso_code' => 'CI'],  // Côte d’Ivoire
            ['id' => 36, 'iso_code' => 'BF'],  // Burkina Faso
            ['id' => 37, 'iso_code' => 'NE'],  // Niger
            ['id' => 38, 'iso_code' => 'TG'],  // Togo
            ['id' => 39, 'iso_code' => 'BJ'],  // Benin
            ['id' => 40, 'iso_code' => 'MU'],  // Mauritius
            ['id' => 41, 'iso_code' => 'LR'],  // Liberia
            ['id' => 42, 'iso_code' => 'SL'],  // Sierra Leone
            ['id' => 43, 'iso_code' => 'GH'],  // Ghana
            ['id' => 44, 'iso_code' => 'NG'],  // Nigeria
            ['id' => 45, 'iso_code' => 'TD'],  // Chad
            ['id' => 46, 'iso_code' => 'CF'],  // Central African Republic
            ['id' => 47, 'iso_code' => 'CM'],  // Cameroon
            ['id' => 48, 'iso_code' => 'CV'],  // Cape Verde
            ['id' => 49, 'iso_code' => 'ST'],  // São Tomé and Príncipe
            ['id' => 50, 'iso_code' => 'GQ'],  // Equatorial Guinea
            ['id' => 51, 'iso_code' => 'GA'],  // Gabon
            ['id' => 52, 'iso_code' => 'CG'],  // Congo
            ['id' => 53, 'iso_code' => 'CD'],  // DR Congo
            ['id' => 54, 'iso_code' => 'AO'],  // Angola
            ['id' => 55, 'iso_code' => 'GW'],  // Guinea-Bissau
            ['id' => 56, 'iso_code' => 'DG'],  // Diego Garcia (custom)
            ['id' => 57, 'iso_code' => 'AC'],  // Ascension Island (custom)
            ['id' => 58, 'iso_code' => 'SC'],  // Seychelles
            ['id' => 59, 'iso_code' => 'SD'],  // Sudan
            ['id' => 60, 'iso_code' => 'RW'],  // Rwanda
            ['id' => 61, 'iso_code' => 'ET'],  // Ethiopia
            ['id' => 62, 'iso_code' => 'SO'],  // Somalia
            ['id' => 63, 'iso_code' => 'DJ'],  // Djibouti
            ['id' => 64, 'iso_code' => 'KE'],  // Kenya
            ['id' => 65, 'iso_code' => 'TZ'],  // Tanzania
            ['id' => 66, 'iso_code' => 'UG'],  // Uganda
            ['id' => 67, 'iso_code' => 'BI'],  // Burundi
            ['id' => 68, 'iso_code' => 'MZ'],  // Mozambique
            ['id' => 69, 'iso_code' => 'ZM'],  // Zambia
            ['id' => 70, 'iso_code' => 'MG'],  // Madagascar
            ['id' => 71, 'iso_code' => 'RE'],  // Réunion
            ['id' => 72, 'iso_code' => 'ZW'],  // Zimbabwe
            ['id' => 73, 'iso_code' => 'NA'],  // Namibia
            ['id' => 74, 'iso_code' => 'MW'],  // Malawi
            ['id' => 75, 'iso_code' => 'LS'],  // Lesotho
            ['id' => 76, 'iso_code' => 'BW'],  // Botswana
            ['id' => 77, 'iso_code' => 'SZ'],  // Swaziland (Eswatini)
            ['id' => 78, 'iso_code' => 'KM'],  // Comoros
            ['id' => 79, 'iso_code' => 'YT'],  // Mayotte
            ['id' => 80, 'iso_code' => 'ZA'],  // South Africa
            // ... continue mapping all the way till ['id' => 230, 'iso_code' => 'XZ']
        ];

        foreach ($isoCodes as $row) {
            DB::table('country')->where('id', $row['id'])->update(['iso_code' => $row['iso_code']]);
        }
    }

    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            if (Schema::hasColumn('country', 'iso_code')) {
                $table->dropColumn('iso_code');
            }
        });
    }
};
