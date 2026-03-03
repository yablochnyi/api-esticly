<?php

// database/seeders/CurrencySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound Sterling', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽'],
            ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
            ['code' => 'KZT', 'name' => 'Kazakhstani Tenge', 'symbol' => '₸'],
            ['code' => 'BYN', 'name' => 'Belarusian Ruble', 'symbol' => 'Br'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => '$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$'],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => '$'],
            ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr'],
            ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr'],
            ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr'],
            ['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł'],
            ['code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč'],
            ['code' => 'HUF', 'name' => 'Hungarian Forint', 'symbol' => 'Ft'],
            ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺'],
            ['code' => 'ILS', 'name' => 'Israeli New Shekel', 'symbol' => '₪'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳'],
            ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$'],
            ['code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$'],
            ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$'],
            ['code' => 'CLP', 'name' => 'Chilean Peso', 'symbol' => '$'],
            ['code' => 'COP', 'name' => 'Colombian Peso', 'symbol' => '$'],
            ['code' => 'PEN', 'name' => 'Peruvian Sol', 'symbol' => 'S/'],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R'],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => '£'],
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦'],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => '﷼'],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
            ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫'],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱'],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => '$'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => '$'],
        ];

        DB::table('currencies')->insert($currencies);
    }
}

