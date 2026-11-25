<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('email', 'khalid.owner@khalidtraders.pk')->first() ?? User::first();
        $adminId = $creator?->id;

        // Fetch locations
        $shop1 = Location::where('type', 'shop')->first();
        $shop2 = Location::where('type', 'shop')->skip(1)->first() ?? $shop1;
        $factory1 = Location::where('type', 'factory')->first();
        $factory2 = Location::where('type', 'factory')->skip(1)->first() ?? $factory1;
        $warehouse = Location::where('type', 'warehouse')->first() ?? $shop1;

        $employeesData = [
            // Shop 1 - Anarkali
            [
                'name' => 'محمد رضوان (Mohammad Rizwan)',
                'location_id' => $shop1->id,
                'designation' => 'سینئر سیلزمین (Senior Salesman)',
                'phone' => '0301-4455667',
                'cnic' => '35202-1928374-1',
                'address' => 'مکان نمبر 14، گلی نمبر 3، نسبت روڈ',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 45000.00,
                'joining_date' => '2025-01-15',
                'status' => 'active',
                'emergency_contact_name' => 'عبدالستار (والد)',
                'emergency_contact_phone' => '0321-9988776',
                'advances' => [
                    ['amount' => 5000, 'date' => '2026-10-05', 'desc' => 'گھریلو راشن کے لیے پیشگی خرچہ'],
                    ['amount' => 3000, 'date' => '2026-10-18', 'desc' => 'بچے کی اسکول فیس کا خرچہ'],
                ],
                'salaries' => [
                    ['amount' => 45000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کی تنخواہ (مکمل ادا شدہ)'],
                ]
            ],
            [
                'name' => 'علی حسن (Ali Hassan)',
                'location_id' => $shop1->id,
                'designation' => 'شاپ ہیلپر و کارٹن ڈسپیچ (Helper)',
                'phone' => '0312-3344556',
                'cnic' => '35201-8877665-3',
                'address' => 'انارکلی بازار، پرانا انارکلی',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 28000.00,
                'joining_date' => '2025-06-01',
                'status' => 'active',
                'emergency_contact_name' => 'حسن رضا (بھائی)',
                'emergency_contact_phone' => '0300-1122334',
                'advances' => [
                    ['amount' => 2000, 'date' => '2026-10-12', 'desc' => 'ہنگامی ذاتی خرچہ'],
                ],
                'salaries' => [
                    ['amount' => 28000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کی تنخواہ'],
                ]
            ],

            // Shop 2 - Shah Alam Market
            [
                'name' => 'کامران احمد (Kamran Ahmed)',
                'location_id' => $shop2->id,
                'designation' => 'کاؤنٹر کیشئر و سیلز انچارج (Counter Cashier)',
                'phone' => '0305-6677889',
                'cnic' => '35202-5544332-5',
                'address' => 'شاہ عالم چوک، رنگ محل',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 40000.00,
                'joining_date' => '2025-03-10',
                'status' => 'active',
                'emergency_contact_name' => 'احمد دین (والد)',
                'emergency_contact_phone' => '0345-4433221',
                'advances' => [
                    ['amount' => 4000, 'date' => '2026-10-08', 'desc' => 'موٹرسائیکل کی مرمت کا پیشگی خرچہ'],
                ],
                'salaries' => [
                    ['amount' => 40000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کی مکمل تنخواہ'],
                ]
            ],
            [
                'name' => 'بلال طارق (Bilal Tariq)',
                'location_id' => $shop2->id,
                'designation' => 'ہول سیل سیلز اسسٹنٹ (Sales Assistant)',
                'phone' => '0322-8899001',
                'cnic' => '35201-3322114-7',
                'address' => 'دہلی گیٹ، اندرون لاہور',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 32000.00,
                'joining_date' => '2025-08-20',
                'status' => 'active',
                'emergency_contact_name' => 'طارق محمود (والد)',
                'emergency_contact_phone' => '0333-7788990',
                'advances' => [
                    ['amount' => 2500, 'date' => '2026-10-14', 'desc' => 'راشن پیشگی خرچہ'],
                ],
                'salaries' => []
            ],

            // Factory 1 - Kot Lakhpat
            [
                'name' => 'استاد بشیر احمد (Ustad Bashir)',
                'location_id' => $factory1->id,
                'designation' => 'ہیڈ کاریگر شو میکر (Master Shoemaker)',
                'phone' => '0300-5566778',
                'cnic' => '35202-6677889-9',
                'address' => 'کوٹ لکھپت، پھاٹک کے قریب',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 60000.00,
                'daily_rate' => 2000.00,
                'joining_date' => '2024-02-01',
                'status' => 'active',
                'emergency_contact_name' => 'محمد بشیر (بیٹا)',
                'emergency_contact_phone' => '0321-4455667',
                'advances' => [
                    ['amount' => 10000, 'date' => '2026-10-02', 'desc' => 'گھریلو راشن و ایمرجنسی پیشگی خرچہ'],
                    ['amount' => 5000, 'date' => '2026-10-15', 'desc' => 'طبی علاج کا خرچہ'],
                ],
                'salaries' => [
                    ['amount' => 60000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کاریگر تنخواہ'],
                ]
            ],
            [
                'name' => 'شاہد پرویز (Shahid Pervez)',
                'location_id' => $factory1->id,
                'designation' => 'لیزر کٹنگ ماسٹر (Cutting Master)',
                'phone' => '0315-7788990',
                'cnic' => '35201-1122334-1',
                'address' => 'چونگی امرسدھو، فیروزپور روڈ',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 48000.00,
                'joining_date' => '2024-11-15',
                'status' => 'active',
                'emergency_contact_name' => 'پرویز اختر',
                'emergency_contact_phone' => '0304-5544332',
                'advances' => [
                    ['amount' => 4000, 'date' => '2026-10-10', 'desc' => 'پیشگی ادھار'],
                ],
                'salaries' => [
                    ['amount' => 48000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کٹنگ تنخواہ'],
                ]
            ],

            // Factory 2
            [
                'name' => 'محمد سلیم (Muhammad Saleem)',
                'location_id' => $factory2->id,
                'designation' => 'سول اسٹچنگ و اسمبلنگ (Sole Stitcher)',
                'phone' => '0340-9988776',
                'cnic' => '35202-4433221-3',
                'address' => 'سندر انڈسٹریل اسٹیٹ',
                'city' => 'Lahore',
                'employment_type' => 'piece_rate',
                'monthly_salary' => 42000.00,
                'joining_date' => '2025-04-01',
                'status' => 'active',
                'emergency_contact_name' => 'عمران سلیم',
                'emergency_contact_phone' => '0311-2233445',
                'advances' => [
                    ['amount' => 3500, 'date' => '2026-10-09', 'desc' => 'پیشگی خرچہ راشن'],
                ],
                'salaries' => []
            ],

            // Warehouse
            [
                'name' => 'عمر دراز (Umar Daraz)',
                'location_id' => $warehouse->id,
                'designation' => 'ویئر ہاؤس انچارج و کارٹن مینجر (Warehouse Incharge)',
                'phone' => '0302-1122334',
                'cnic' => '35201-9988776-5',
                'address' => 'بادامی باغ گودام، لاہور',
                'city' => 'Lahore',
                'employment_type' => 'monthly',
                'monthly_salary' => 50000.00,
                'joining_date' => '2024-05-10',
                'status' => 'active',
                'emergency_contact_name' => 'سلیم اختر',
                'emergency_contact_phone' => '0300-8877665',
                'advances' => [
                    ['amount' => 6000, 'date' => '2026-10-04', 'desc' => 'گھر کا کرایہ پیشگی خرچہ'],
                ],
                'salaries' => [
                    ['amount' => 50000, 'date' => '2026-09-30', 'month' => 'September 2026', 'desc' => 'ماہ ستمبر کی مکمل تنخواہ'],
                ]
            ],
        ];

        foreach ($employeesData as $data) {
            $advances = $data['advances'] ?? [];
            $salaries = $data['salaries'] ?? [];
            unset($data['advances'], $data['salaries']);

            $data['employee_code'] = Employee::generateCode($data['location_id']);
            $data['created_by'] = $adminId;

            $emp = Employee::create($data);

            // Seed sample advances / kharcha
            foreach ($advances as $adv) {
                EmployeeTransaction::create([
                    'transaction_number' => EmployeeTransaction::generateTransactionNumber('advance_kharcha'),
                    'employee_id' => $emp->id,
                    'location_id' => $emp->location_id,
                    'type' => 'advance_kharcha',
                    'amount' => $adv['amount'],
                    'transaction_date' => $adv['date'],
                    'month_year' => 'October 2026',
                    'payment_method' => 'cash',
                    'description' => $adv['desc'],
                    'recorded_by' => $adminId,
                ]);
            }

            // Seed sample salaries
            foreach ($salaries as $sal) {
                EmployeeTransaction::create([
                    'transaction_number' => EmployeeTransaction::generateTransactionNumber('salary_payment'),
                    'employee_id' => $emp->id,
                    'location_id' => $emp->location_id,
                    'type' => 'salary_payment',
                    'amount' => $sal['amount'],
                    'transaction_date' => $sal['date'],
                    'month_year' => $sal['month'],
                    'payment_method' => 'cash',
                    'description' => $sal['desc'],
                    'recorded_by' => $adminId,
                ]);
            }
        }
    }
}
