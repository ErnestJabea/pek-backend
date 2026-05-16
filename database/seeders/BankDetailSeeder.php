<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BankDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\BankDetail::create([
            'bank_name' => 'Banque Atlantique Bénin',
            'iban' => 'BJ66 0400 1200 0000 1234 5678',
            'rib' => '12345678901',
            'swift' => 'BATLBJCC',
            'is_active' => true,
        ]);
    }
}
