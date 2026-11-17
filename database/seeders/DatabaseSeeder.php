<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with essential settings and user login credentials only.
     */
    public function run(): void
    {
        // 1. System Settings Configuration
        $defaultSettings = [
            'app_name' => ['value' => 'AI Invoice to Excel', 'group' => 'general', 'description' => 'System application name'],
            'gemini_api_key' => ['value' => '', 'group' => 'ai', 'description' => 'Google Gemini API Key for intelligent invoice parsing'],
            'gemini_model' => ['value' => 'gemini-1.5-flash', 'group' => 'ai', 'description' => 'Target Gemini model for invoice data extraction'],
            'default_currency' => ['value' => 'INR', 'group' => 'general', 'description' => 'Default currency code (Indian Rupee - ₹)'],
            'matching_tolerance_percent' => ['value' => '2.00', 'group' => 'matching', 'description' => 'Allowed variance percentage for automated PO/Shipping matching'],
            'ocr_engine' => ['value' => 'tesseract', 'group' => 'ocr', 'description' => 'OCR engine (tesseract, cloud, pdf_native)'],
            'export_default_format' => ['value' => 'xlsx', 'group' => 'export', 'description' => 'Default Excel export format'],
            'auto_process_on_upload' => ['value' => '1', 'group' => 'general', 'description' => 'Queue extraction immediately upon document upload'],
        ];

        foreach ($defaultSettings as $key => $meta) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $meta['value'],
                    'group' => $meta['group'],
                    'description' => $meta['description'],
                ]
            );
        }

        // 2. Administrator Login Account
        User::updateOrCreate(
            ['email' => 'admin@invoiceexcel.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'phone' => '+91 98765 43210',
            ]
        );

        // 3. Standard User Login Account
        User::updateOrCreate(
            ['email' => 'user@invoiceexcel.test'],
            [
                'name' => 'Operations Specialist',
                'password' => Hash::make('password'),
                'role' => User::ROLE_USER,
                'status' => 'active',
                'phone' => '+91 98765 43211',
            ]
        );
    }
}
