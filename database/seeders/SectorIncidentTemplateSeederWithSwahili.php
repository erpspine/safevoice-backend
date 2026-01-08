<?php

namespace Database\Seeders;

use App\Models\SectorIncidentTemplate;
use Illuminate\Database\Seeder;

class SectorIncidentTemplateSeederWithSwahili extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = $this->getSectorTemplates();

        foreach ($templates as $sector => $categories) {
            $categoryOrder = 1;
            foreach ($categories as $category) {
                $subcategoryOrder = 1;

                // Create the parent category entry
                SectorIncidentTemplate::updateOrCreate(
                    [
                        'sector' => $sector,
                        'category_key' => $category['key'],
                        'subcategory_name' => null,
                    ],
                    [
                        'category_name' => $category['name'],
                        'category_name_sw' => $category['name_sw'] ?? null,
                        'description' => $category['description'] ?? null,
                        'description_sw' => $category['description_sw'] ?? null,
                        'sort_order' => $categoryOrder * 100,
                        'status' => true,
                    ]
                );

                // Create subcategory entries
                foreach ($category['subcategories'] ?? [] as $subcategory) {
                    SectorIncidentTemplate::updateOrCreate(
                        [
                            'sector' => $sector,
                            'category_key' => $category['key'],
                            'subcategory_name' => $subcategory['name'],
                        ],
                        [
                            'category_name' => $category['name'],
                            'category_name_sw' => $category['name_sw'] ?? null,
                            'subcategory_name_sw' => $subcategory['name_sw'] ?? null,
                            'description' => null,
                            'sort_order' => ($categoryOrder * 100) + $subcategoryOrder,
                            'status' => true,
                        ]
                    );
                    $subcategoryOrder++;
                }

                $categoryOrder++;
            }
        }
    }

    /**
     * Get all sector templates configuration with Swahili translations.
     */
    private function getSectorTemplates(): array
    {
        return [
            // EDUCATION SECTOR
            'education' => [
                [
                    'key' => 'academic_misconduct',
                    'name' => 'Academic Misconduct',
                    'name_sw' => 'Tabia Mbaya za Kitaaluma',
                    'subcategories' => [
                        ['name' => 'Plagiarism', 'name_sw' => 'Ulaghai'],
                        ['name' => 'Exam cheating', 'name_sw' => 'Udanganyifu wa Mtihani'],
                        ['name' => 'Grade manipulation', 'name_sw' => 'Ughushi wa Alama'],
                        ['name' => 'Falsification of academic records', 'name_sw' => 'Ubadilikaji wa Kumbukumbu za Kitaaluma'],
                    ],
                ],
                [
                    'key' => 'student_welfare',
                    'name' => 'Student Welfare',
                    'name_sw' => 'Ustawi wa Wanafunzi',
                    'subcategories' => [
                        ['name' => 'Bullying', 'name_sw' => 'Uonevu'],
                        ['name' => 'Cyberbullying', 'name_sw' => 'Uonevu wa Mtandaoni'],
                        ['name' => 'Harassment', 'name_sw' => 'Udhalimu'],
                        ['name' => 'Mental health concerns', 'name_sw' => 'Matatizo ya Afya ya Akili'],
                        ['name' => 'Self-harm or suicidal ideation', 'name_sw' => 'Kujiumiza au Nia za Kujiua'],
                    ],
                ],
                [
                    'key' => 'staff_misconduct',
                    'name' => 'Staff Misconduct',
                    'name_sw' => 'Tabia Mbaya za Wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Inappropriate relationships with students', 'name_sw' => 'Mahusiano Yasiyo ya Kawaida na Wanafunzi'],
                        ['name' => 'Abuse of authority', 'name_sw' => 'Matumizi Mabaya ya Mamlaka'],
                        ['name' => 'Neglect of duty', 'name_sw' => 'Kupuuza Wajibu'],
                        ['name' => 'Breach of professional ethics', 'name_sw' => 'Ukiukaji wa Maadili ya Kitaaluma'],
                    ],
                ],
                [
                    'key' => 'safeguarding',
                    'name' => 'Safeguarding',
                    'name_sw' => 'Ulinzi',
                    'subcategories' => [
                        ['name' => 'Child protection concerns', 'name_sw' => 'Wasiwasi wa Ulinzi wa Watoto'],
                        ['name' => 'Physical abuse', 'name_sw' => 'Unyanyasaji wa Kimwili'],
                        ['name' => 'Emotional abuse', 'name_sw' => 'Unyanyasaji wa Kihisia'],
                        ['name' => 'Sexual abuse', 'name_sw' => 'Unyanyasaji wa Kingono'],
                        ['name' => 'Neglect', 'name_sw' => 'Upuuzi'],
                    ],
                ],
                [
                    'key' => 'financial_mismanagement',
                    'name' => 'Financial Mismanagement',
                    'name_sw' => 'Usimamizi Mbaya wa Fedha',
                    'subcategories' => [
                        ['name' => 'Misuse of school funds', 'name_sw' => 'Matumizi Mabaya ya Fedha za Shule'],
                        ['name' => 'Fraudulent procurement', 'name_sw' => 'Ununuzi wa Ughushi'],
                        ['name' => 'Bribery in admissions', 'name_sw' => 'Rushwa katika Uandikishaji'],
                        ['name' => 'Unauthorized fee collection', 'name_sw' => 'Ukusanyaji wa Ada bila Idhini'],
                    ],
                ],
                [
                    'key' => 'safety_security',
                    'name' => 'Safety & Security',
                    'name_sw' => 'Usalama na Ulinzi',
                    'subcategories' => [
                        ['name' => 'Unsafe premises', 'name_sw' => 'Mazingira Yasiyo Salama'],
                        ['name' => 'Lack of emergency preparedness', 'name_sw' => 'Ukosefu wa Utayari kwa Dharura'],
                        ['name' => 'Substance abuse on campus', 'name_sw' => 'Matumizi Mabaya ya Dawa Haramani'],
                        ['name' => 'Unauthorized visitors', 'name_sw' => 'Wageni Wasio na Idhini'],
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'name_sw' => 'Ubaguzi',
                    'subcategories' => [
                        ['name' => 'Racial discrimination', 'name_sw' => 'Ubaguzi wa Rangi'],
                        ['name' => 'Gender discrimination', 'name_sw' => 'Ubaguzi wa Jinsia'],
                        ['name' => 'Disability discrimination', 'name_sw' => 'Ubaguzi wa Ulemavu'],
                        ['name' => 'Religious discrimination', 'name_sw' => 'Ubaguzi wa Dini'],
                    ],
                ],
            ],

            // CORPORATE WORKPLACE SECTOR
            'corporate_workplace' => [
                [
                    'key' => 'workplace_harassment',
                    'name' => 'Workplace Harassment',
                    'name_sw' => 'Udhalimu Kazini',
                    'subcategories' => [
                        ['name' => 'Sexual harassment', 'name_sw' => 'Udhalimu wa Kingono'],
                        ['name' => 'Verbal abuse', 'name_sw' => 'Matusi ya Maneno'],
                        ['name' => 'Bullying', 'name_sw' => 'Uonevu'],
                        ['name' => 'Intimidation', 'name_sw' => 'Kutisha'],
                        ['name' => 'Hostile work environment', 'name_sw' => 'Mazingira ya Uhasama Kazini'],
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'name_sw' => 'Ubaguzi',
                    'subcategories' => [
                        ['name' => 'Gender discrimination', 'name_sw' => 'Ubaguzi wa Jinsia'],
                        ['name' => 'Racial discrimination', 'name_sw' => 'Ubaguzi wa Rangi'],
                        ['name' => 'Age discrimination', 'name_sw' => 'Ubaguzi wa Umri'],
                        ['name' => 'Disability discrimination', 'name_sw' => 'Ubaguzi wa Ulemavu'],
                        ['name' => 'Religious discrimination', 'name_sw' => 'Ubaguzi wa Dini'],
                    ],
                ],
                [
                    'key' => 'financial_fraud',
                    'name' => 'Financial Fraud',
                    'name_sw' => 'Ughushi wa Kifedha',
                    'subcategories' => [
                        ['name' => 'Embezzlement', 'name_sw' => 'Ubadhirifu'],
                        ['name' => 'Expense fraud', 'name_sw' => 'Ughushi wa Gharama'],
                        ['name' => 'Invoice manipulation', 'name_sw' => 'Ughushi wa Ankara'],
                        ['name' => 'Payroll fraud', 'name_sw' => 'Ughushi wa Mishahara'],
                        ['name' => 'Asset misappropriation', 'name_sw' => 'Utumizi Mbaya wa Mali'],
                    ],
                ],
                [
                    'key' => 'conflict_of_interest',
                    'name' => 'Conflict of Interest',
                    'name_sw' => 'Mgongano wa Maslahi',
                    'subcategories' => [
                        ['name' => 'Undisclosed relationships', 'name_sw' => 'Uhusiano Usiojulishwa'],
                        ['name' => 'Outside business interests', 'name_sw' => 'Maslahi ya Biashara ya Nje'],
                        ['name' => 'Favoritism in hiring', 'name_sw' => 'Upendeleo katika Kuajiri'],
                        ['name' => 'Nepotism', 'name_sw' => 'Ubaguzi wa Jamaa'],
                    ],
                ],
                [
                    'key' => 'data_privacy_breach',
                    'name' => 'Data Privacy Breach',
                    'name_sw' => 'Ukiukaji wa Faragha ya Data',
                    'subcategories' => [
                        ['name' => 'Unauthorized data access', 'name_sw' => 'Upatikanaji wa Data bila Idhini'],
                        ['name' => 'Data theft', 'name_sw' => 'Wizi wa Data'],
                        ['name' => 'GDPR violations', 'name_sw' => 'Ukiukaji wa GDPR'],
                        ['name' => 'Customer data misuse', 'name_sw' => 'Matumizi Mabaya ya Data ya Wateja'],
                    ],
                ],
                [
                    'key' => 'health_safety',
                    'name' => 'Health & Safety',
                    'name_sw' => 'Afya na Usalama',
                    'subcategories' => [
                        ['name' => 'Unsafe working conditions', 'name_sw' => 'Mazingira Yasiyosalama ya Kazi'],
                        ['name' => 'Failure to report accidents', 'name_sw' => 'Kushindwa Kuripoti Ajali'],
                        ['name' => 'Violation of safety protocols', 'name_sw' => 'Ukiukaji wa Taratibu za Usalama'],
                        ['name' => 'Inadequate PPE', 'name_sw' => 'Ukosefu wa Vifaa vya Kinga'],
                    ],
                ],
                [
                    'key' => 'policy_violations',
                    'name' => 'Policy Violations',
                    'name_sw' => 'Ukiukaji wa Sera',
                    'subcategories' => [
                        ['name' => 'Code of conduct breaches', 'name_sw' => 'Ukiukaji wa Kanuni za Tabia'],
                        ['name' => 'IT policy violations', 'name_sw' => 'Ukiukaji wa Sera za TEHAMA'],
                        ['name' => 'Attendance fraud', 'name_sw' => 'Ughushi wa Mahudhurio'],
                        ['name' => 'Substance abuse', 'name_sw' => 'Matumizi Mabaya ya Dawa'],
                    ],
                ],
                [
                    'key' => 'retaliation',
                    'name' => 'Retaliation',
                    'name_sw' => 'Kulipiza Kisasi',
                    'subcategories' => [
                        ['name' => 'Whistleblower retaliation', 'name_sw' => 'Kulipiza Kisasi Wapiga Filimbi'],
                        ['name' => 'Demotion after complaint', 'name_sw' => 'Kushushwa Cheo baada ya Malalamiko'],
                        ['name' => 'Unfair termination', 'name_sw' => 'Kufukuzwa kwa Dhuluma'],
                        ['name' => 'Exclusion from opportunities', 'name_sw' => 'Kuzuiwa Fursa'],
                    ],
                ],
            ],

            // FINANCIAL & INSURANCE SECTOR
            'financial_insurance' => [
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'name_sw' => 'Ughushi',
                    'subcategories' => [
                        ['name' => 'Insurance fraud', 'name_sw' => 'Ughushi wa Bima'],
                        ['name' => 'Claims manipulation', 'name_sw' => 'Ughushi wa Madai'],
                        ['name' => 'Identity theft', 'name_sw' => 'Wizi wa Utambulisho'],
                        ['name' => 'Money laundering', 'name_sw' => 'Utapeli wa Fedha'],
                        ['name' => 'Insider trading', 'name_sw' => 'Biashara ya Siri'],
                    ],
                ],
                [
                    'key' => 'regulatory_violations',
                    'name' => 'Regulatory Violations',
                    'name_sw' => 'Ukiukaji wa Kanuni',
                    'subcategories' => [
                        ['name' => 'AML violations', 'name_sw' => 'Ukiukaji wa Kanuni za Utapeli wa Fedha'],
                        ['name' => 'KYC non-compliance', 'name_sw' => 'Kutofuata Utaratibu wa KYC'],
                        ['name' => 'Licensing breaches', 'name_sw' => 'Ukiukaji wa Leseni'],
                        ['name' => 'Reporting failures', 'name_sw' => 'Kushindwa Kuripoti'],
                    ],
                ],
                [
                    'key' => 'customer_harm',
                    'name' => 'Customer Harm',
                    'name_sw' => 'Madhara kwa Wateja',
                    'subcategories' => [
                        ['name' => 'Mis-selling of products', 'name_sw' => 'Uuzaji wa Udanganyifu wa Bidhaa'],
                        ['name' => 'Unauthorized transactions', 'name_sw' => 'Miamala bila Idhini'],
                        ['name' => 'Unfair lending practices', 'name_sw' => 'Mazoea Yasiyo Sawa ya Mkopo'],
                        ['name' => 'Privacy violations', 'name_sw' => 'Ukiukaji wa Faragha'],
                    ],
                ],
                [
                    'key' => 'conflict_of_interest',
                    'name' => 'Conflict of Interest',
                    'name_sw' => 'Mgongano wa Maslahi',
                    'subcategories' => [
                        ['name' => 'Self-dealing', 'name_sw' => 'Kujinufaisha'],
                        ['name' => 'Related party transactions', 'name_sw' => 'Miamala ya Jamaa'],
                        ['name' => 'Undisclosed commissions', 'name_sw' => 'Mahesabu Yasiyojulishwa'],
                    ],
                ],
                [
                    'key' => 'workplace_misconduct',
                    'name' => 'Workplace Misconduct',
                    'name_sw' => 'Tabia Mbaya Kazini',
                    'subcategories' => [
                        ['name' => 'Harassment', 'name_sw' => 'Udhalimu'],
                        ['name' => 'Discrimination', 'name_sw' => 'Ubaguzi'],
                        ['name' => 'Bullying', 'name_sw' => 'Uonevu'],
                        ['name' => 'Retaliation', 'name_sw' => 'Kulipiza Kisasi'],
                    ],
                ],
                [
                    'key' => 'cybersecurity',
                    'name' => 'Cybersecurity',
                    'name_sw' => 'Usalama wa Mtandao',
                    'subcategories' => [
                        ['name' => 'Data breaches', 'name_sw' => 'Uvujaji wa Data'],
                        ['name' => 'Phishing incidents', 'name_sw' => 'Matukio ya Udanganyifu wa Mtandao'],
                        ['name' => 'System vulnerabilities', 'name_sw' => 'Udhaifu wa Mfumo'],
                        ['name' => 'Unauthorized access', 'name_sw' => 'Upatikanaji bila Idhini'],
                    ],
                ],
            ],

            // HEALTHCARE SECTOR
            'healthcare' => [
                [
                    'key' => 'patient_safety',
                    'name' => 'Patient Safety',
                    'name_sw' => 'Usalama wa Wagonjwa',
                    'subcategories' => [
                        ['name' => 'Medical errors', 'name_sw' => 'Makosa ya Kimatibabu'],
                        ['name' => 'Medication errors', 'name_sw' => 'Makosa ya Dawa'],
                        ['name' => 'Surgical complications', 'name_sw' => 'Matatizo ya Upasuaji'],
                        ['name' => 'Misdiagnosis', 'name_sw' => 'Utambuzi Mbaya wa Ugonjwa'],
                    ],
                ],
                [
                    'key' => 'professional_misconduct',
                    'name' => 'Professional Misconduct',
                    'name_sw' => 'Tabia Mbaya za Kitaaluma',
                    'subcategories' => [
                        ['name' => 'Negligence', 'name_sw' => 'Uzembe'],
                        ['name' => 'Incompetence', 'name_sw' => 'Ukosefu wa Ujuzi'],
                        ['name' => 'Boundary violations', 'name_sw' => 'Ukiukaji wa Mipaka'],
                        ['name' => 'Breach of confidentiality', 'name_sw' => 'Ukiukaji wa Siri'],
                    ],
                ],
                [
                    'key' => 'patient_abuse',
                    'name' => 'Patient Abuse',
                    'name_sw' => 'Unyanyasaji wa Wagonjwa',
                    'subcategories' => [
                        ['name' => 'Physical abuse', 'name_sw' => 'Unyanyasaji wa Kimwili'],
                        ['name' => 'Emotional abuse', 'name_sw' => 'Unyanyasaji wa Kihisia'],
                        ['name' => 'Sexual abuse', 'name_sw' => 'Unyanyasaji wa Kingono'],
                        ['name' => 'Neglect of vulnerable patients', 'name_sw' => 'Kupuuza Wagonjwa Dhaifu'],
                    ],
                ],
                [
                    'key' => 'billing_fraud',
                    'name' => 'Billing Fraud',
                    'name_sw' => 'Ughushi wa Malipo',
                    'subcategories' => [
                        ['name' => 'Overbilling', 'name_sw' => 'Kulipisha Zaidi'],
                        ['name' => 'Phantom billing', 'name_sw' => 'Ankara za Udanganyifu'],
                        ['name' => 'Unbundling services', 'name_sw' => 'Kugawanya Huduma kwa Ughushi'],
                        ['name' => 'Kickbacks', 'name_sw' => 'Rushwa ya Huduma'],
                    ],
                ],
                [
                    'key' => 'drug_diversion',
                    'name' => 'Drug Diversion',
                    'name_sw' => 'Uelekeo Mbaya wa Dawa',
                    'subcategories' => [
                        ['name' => 'Theft of medication', 'name_sw' => 'Wizi wa Dawa'],
                        ['name' => 'Misuse of controlled substances', 'name_sw' => 'Matumizi Mabaya ya Dawa za Kudhibiti'],
                        ['name' => 'Prescription fraud', 'name_sw' => 'Ughushi wa Maagizo ya Dawa'],
                    ],
                ],
                [
                    'key' => 'infection_control',
                    'name' => 'Infection Control',
                    'name_sw' => 'Udhibiti wa Maambukizi',
                    'subcategories' => [
                        ['name' => 'Poor hygiene practices', 'name_sw' => 'Usafi Mbaya'],
                        ['name' => 'Sterilization failures', 'name_sw' => 'Kushindwa Kusafisha'],
                        ['name' => 'HAI prevention lapses', 'name_sw' => 'Kukosa Uzuiaji wa Maambukizi'],
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'name_sw' => 'Ubaguzi',
                    'subcategories' => [
                        ['name' => 'Denial of care based on race', 'name_sw' => 'Kukataa Huduma kwa Ubaguzi wa Rangi'],
                        ['name' => 'Disability discrimination', 'name_sw' => 'Ubaguzi wa Ulemavu'],
                        ['name' => 'Gender-based discrimination', 'name_sw' => 'Ubaguzi wa Jinsia'],
                    ],
                ],
            ],

            // MANUFACTURING & INDUSTRIAL SECTOR
            'manufacturing_industrial' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'name_sw' => 'Ukiukaji wa Usalama',
                    'subcategories' => [
                        ['name' => 'Unsafe machinery operation', 'name_sw' => 'Uendeshaji Usiofaa wa Mashine'],
                        ['name' => 'Lack of safety equipment', 'name_sw' => 'Ukosefu wa Vifaa vya Usalama'],
                        ['name' => 'Failure to follow protocols', 'name_sw' => 'Kutofuata Taratibu'],
                        ['name' => 'Inadequate training', 'name_sw' => 'Mafunzo Yasiyotosha'],
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'name_sw' => 'Ukiukaji wa Mazingira',
                    'subcategories' => [
                        ['name' => 'Illegal waste disposal', 'name_sw' => 'Kutupa Taka kwa Ufisadi'],
                        ['name' => 'Pollution incidents', 'name_sw' => 'Matukio ya Uchafuzi'],
                        ['name' => 'Emissions violations', 'name_sw' => 'Ukiukaji wa Uzalishaji wa Hewa'],
                        ['name' => 'Failure to report spills', 'name_sw' => 'Kushindwa Kuripoti Umwagikaji'],
                    ],
                ],
                [
                    'key' => 'quality_control',
                    'name' => 'Quality Control',
                    'name_sw' => 'Udhibiti wa Ubora',
                    'subcategories' => [
                        ['name' => 'Product defects', 'name_sw' => 'Dosari za Bidhaa'],
                        ['name' => 'Falsification of quality reports', 'name_sw' => 'Ubadilikaji wa Ripoti za Ubora'],
                        ['name' => 'Use of substandard materials', 'name_sw' => 'Matumizi ya Vifaa vya Chini'],
                        ['name' => 'Tampering with testing equipment', 'name_sw' => 'Kucheza na Vifaa vya Upimaji'],
                    ],
                ],
                [
                    'key' => 'labor_rights',
                    'name' => 'Labor Rights',
                    'name_sw' => 'Haki za Wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Forced labor', 'name_sw' => 'Kazi ya Kulazimishwa'],
                        ['name' => 'Child labor', 'name_sw' => 'Kazi ya Watoto'],
                        ['name' => 'Wage theft', 'name_sw' => 'Wizi wa Mishahara'],
                        ['name' => 'Union suppression', 'name_sw' => 'Kukandamiza Vyama vya Wafanyakazi'],
                    ],
                ],
                [
                    'key' => 'theft_sabotage',
                    'name' => 'Theft & Sabotage',
                    'name_sw' => 'Wizi na Ughauzi',
                    'subcategories' => [
                        ['name' => 'Theft of materials', 'name_sw' => 'Wizi wa Vifaa'],
                        ['name' => 'Equipment sabotage', 'name_sw' => 'Uharibifu wa Vifaa'],
                        ['name' => 'Intellectual property theft', 'name_sw' => 'Wizi wa Mali Isiyogusika'],
                        ['name' => 'Trade secret leaks', 'name_sw' => 'Uvujaji wa Siri za Biashara'],
                    ],
                ],
                [
                    'key' => 'workplace_harassment',
                    'name' => 'Workplace Harassment',
                    'name_sw' => 'Udhalimu Kazini',
                    'subcategories' => [
                        ['name' => 'Sexual harassment', 'name_sw' => 'Udhalimu wa Kingono'],
                        ['name' => 'Bullying', 'name_sw' => 'Uonevu'],
                        ['name' => 'Discrimination', 'name_sw' => 'Ubaguzi'],
                    ],
                ],
            ],

            // CONSTRUCTION & ENGINEERING SECTOR
            'construction_engineering' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'name_sw' => 'Ukiukaji wa Usalama',
                    'subcategories' => [
                        ['name' => 'Fall protection failures', 'name_sw' => 'Kushindwa Kulinda dhidi ya Kuanguka'],
                        ['name' => 'Unsafe scaffolding', 'name_sw' => 'Vifaa vya Ujenzi Visivyo Salama'],
                        ['name' => 'Electrical hazards', 'name_sw' => 'Hatari za Umeme'],
                        ['name' => 'Missing PPE', 'name_sw' => 'Ukosefu wa Vifaa vya Kinga'],
                    ],
                ],
                [
                    'key' => 'quality_fraud',
                    'name' => 'Quality Fraud',
                    'name_sw' => 'Ughushi wa Ubora',
                    'subcategories' => [
                        ['name' => 'Use of substandard materials', 'name_sw' => 'Matumizi ya Vifaa vya Chini'],
                        ['name' => 'Deviation from specifications', 'name_sw' => 'Kutofuata Maelezo'],
                        ['name' => 'Falsified inspections', 'name_sw' => 'Ukaguzi wa Udanganyifu'],
                        ['name' => 'Structural defects', 'name_sw' => 'Dosari za Muundo'],
                    ],
                ],
                [
                    'key' => 'procurement_fraud',
                    'name' => 'Procurement Fraud',
                    'name_sw' => 'Ughushi wa Ununuzi',
                    'subcategories' => [
                        ['name' => 'Kickbacks to contractors', 'name_sw' => 'Rushwa kwa Wakandarasi'],
                        ['name' => 'Bid rigging', 'name_sw' => 'Ughushi wa Zabuni'],
                        ['name' => 'Inflated invoicing', 'name_sw' => 'Ankara zilizopunguzwa'],
                        ['name' => 'Phantom suppliers', 'name_sw' => 'Wasambazaji wa Udanganyifu'],
                    ],
                ],
                [
                    'key' => 'labor_exploitation',
                    'name' => 'Labor Exploitation',
                    'name_sw' => 'Unyonyaji wa Wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Wage theft', 'name_sw' => 'Wizi wa Mishahara'],
                        ['name' => 'Illegal workers', 'name_sw' => 'Wafanyakazi Haramu'],
                        ['name' => 'Unsafe conditions', 'name_sw' => 'Mazingira Yasiyosalama'],
                        ['name' => 'Denial of benefits', 'name_sw' => 'Kukataliwa Faida'],
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'name_sw' => 'Ukiukaji wa Mazingira',
                    'subcategories' => [
                        ['name' => 'Illegal dumping', 'name_sw' => 'Kutupa Taka kwa Ufisadi'],
                        ['name' => 'Pollution', 'name_sw' => 'Uchafuzi'],
                        ['name' => 'Unauthorized land clearing', 'name_sw' => 'Kukata Miti bila Idhini'],
                    ],
                ],
                [
                    'key' => 'permit_violations',
                    'name' => 'Permit Violations',
                    'name_sw' => 'Ukiukaji wa Vibali',
                    'subcategories' => [
                        ['name' => 'Operating without permits', 'name_sw' => 'Kufanya Kazi bila Vibali'],
                        ['name' => 'Expired licenses', 'name_sw' => 'Leseni Zilizokwisha'],
                        ['name' => 'Unauthorized modifications', 'name_sw' => 'Mabadiliko Yasiyoidhinishwa'],
                    ],
                ],
            ],

            // SECURITY & UNIFORMED SERVICES SECTOR
            'security_uniformed_services' => [
                [
                    'key' => 'excessive_force',
                    'name' => 'Excessive Force',
                    'name_sw' => 'Nguvu Kupita Kiasi',
                    'subcategories' => [
                        ['name' => 'Physical brutality', 'name_sw' => 'Ukali wa Kimwili'],
                        ['name' => 'Unnecessary use of weapons', 'name_sw' => 'Matumizi ya Silaha bila Haja'],
                        ['name' => 'Torture', 'name_sw' => 'Mateso'],
                        ['name' => 'Abuse of authority', 'name_sw' => 'Matumizi Mabaya ya Mamlaka'],
                    ],
                ],
                [
                    'key' => 'corruption',
                    'name' => 'Corruption',
                    'name_sw' => 'Ufisadi',
                    'subcategories' => [
                        ['name' => 'Bribery', 'name_sw' => 'Rushwa'],
                        ['name' => 'Extortion', 'name_sw' => 'Ulanguzi'],
                        ['name' => 'Evidence tampering', 'name_sw' => 'Kubadilisha Ushahidi'],
                        ['name' => 'False arrests', 'name_sw' => 'Makamatisho ya Udanganyifu'],
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'name_sw' => 'Ubaguzi',
                    'subcategories' => [
                        ['name' => 'Racial profiling', 'name_sw' => 'Ubaguzi wa Rangi'],
                        ['name' => 'Religious bias', 'name_sw' => 'Upendeleo wa Dini'],
                        ['name' => 'Gender discrimination', 'name_sw' => 'Ubaguzi wa Jinsia'],
                        ['name' => 'Socioeconomic targeting', 'name_sw' => 'Kulenga Kulingana na Uchumi'],
                    ],
                ],
                [
                    'key' => 'negligence',
                    'name' => 'Negligence',
                    'name_sw' => 'Uzembe',
                    'subcategories' => [
                        ['name' => 'Failure to respond', 'name_sw' => 'Kushindwa Kujibu'],
                        ['name' => 'Dereliction of duty', 'name_sw' => 'Kupuuza Wajibu'],
                        ['name' => 'Poor investigation', 'name_sw' => 'Uchunguzi Mbaya'],
                        ['name' => 'Loss of evidence', 'name_sw' => 'Kupoteza Ushahidi'],
                    ],
                ],
                [
                    'key' => 'sexual_misconduct',
                    'name' => 'Sexual Misconduct',
                    'name_sw' => 'Tabia Mbaya za Kingono',
                    'subcategories' => [
                        ['name' => 'Sexual harassment', 'name_sw' => 'Udhalimu wa Kingono'],
                        ['name' => 'Sexual assault', 'name_sw' => 'Kushambulia Kingono'],
                        ['name' => 'Coercion', 'name_sw' => 'Kulazimisha'],
                    ],
                ],
                [
                    'key' => 'policy_violations',
                    'name' => 'Policy Violations',
                    'name_sw' => 'Ukiukaji wa Sera',
                    'subcategories' => [
                        ['name' => 'Breach of protocol', 'name_sw' => 'Ukiukaji wa Taratibu'],
                        ['name' => 'Unauthorized use of force', 'name_sw' => 'Matumizi ya Nguvu bila Idhini'],
                        ['name' => 'Equipment misuse', 'name_sw' => 'Matumizi Mabaya ya Vifaa'],
                    ],
                ],
            ],

            // HOSPITALITY, TRAVEL & TOURISM SECTOR
            'hospitality_travel_tourism' => [
                [
                    'key' => 'customer_safety',
                    'name' => 'Customer Safety',
                    'name_sw' => 'Usalama wa Wateja',
                    'subcategories' => [
                        ['name' => 'Food safety violations', 'name_sw' => 'Ukiukaji wa Usalama wa Chakula'],
                        ['name' => 'Unsafe facilities', 'name_sw' => 'Vifaa Visivyo Salama'],
                        ['name' => 'Fire safety violations', 'name_sw' => 'Ukiukaji wa Usalama wa Moto'],
                        ['name' => 'Security lapses', 'name_sw' => 'Kukosa Usalama'],
                    ],
                ],
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'name_sw' => 'Ughushi',
                    'subcategories' => [
                        ['name' => 'Billing fraud', 'name_sw' => 'Ughushi wa Malipo'],
                        ['name' => 'False advertising', 'name_sw' => 'Matangazo ya Uongo'],
                        ['name' => 'Theft of customer property', 'name_sw' => 'Wizi wa Mali ya Wateja'],
                        ['name' => 'Credit card fraud', 'name_sw' => 'Ughushi wa Kadi ya Mkopo'],
                    ],
                ],
                [
                    'key' => 'harassment',
                    'name' => 'Harassment',
                    'name_sw' => 'Udhalimu',
                    'subcategories' => [
                        ['name' => 'Sexual harassment of guests', 'name_sw' => 'Udhalimu wa Kingono kwa Wageni'],
                        ['name' => 'Workplace harassment', 'name_sw' => 'Udhalimu Kazini'],
                        ['name' => 'Discrimination', 'name_sw' => 'Ubaguzi'],
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'name_sw' => 'Ukiukaji wa Haki za Wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Wage theft', 'name_sw' => 'Wizi wa Mishahara'],
                        ['name' => 'Excessive working hours', 'name_sw' => 'Masaa ya Kazi Kupita Kiasi'],
                        ['name' => 'Denial of benefits', 'name_sw' => 'Kukataliwa Faida'],
                        ['name' => 'Child labor', 'name_sw' => 'Kazi ya Watoto'],
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'name_sw' => 'Ukiukaji wa Mazingira',
                    'subcategories' => [
                        ['name' => 'Illegal waste disposal', 'name_sw' => 'Kutupa Taka kwa Ufisadi'],
                        ['name' => 'Pollution', 'name_sw' => 'Uchafuzi'],
                        ['name' => 'Wildlife exploitation', 'name_sw' => 'Unyonyaji wa Wanyama Pori'],
                    ],
                ],
                [
                    'key' => 'licensing_violations',
                    'name' => 'Licensing Violations',
                    'name_sw' => 'Ukiukaji wa Leseni',
                    'subcategories' => [
                        ['name' => 'Operating without proper licenses', 'name_sw' => 'Kufanya Kazi bila Leseni Sahihi'],
                        ['name' => 'Expired permits', 'name_sw' => 'Vibali Vilivyokwisha'],
                        ['name' => 'Health code violations', 'name_sw' => 'Ukiukaji wa Kanuni za Afya'],
                    ],
                ],
            ],

            // NGO, CSO & DONOR-FUNDED SECTOR
            'ngo_cso_donor_funded' => [
                [
                    'key' => 'financial_fraud',
                    'name' => 'Financial Fraud',
                    'name_sw' => 'Ughushi wa Kifedha',
                    'subcategories' => [
                        ['name' => 'Misappropriation of funds', 'name_sw' => 'Utumizi Mbaya wa Fedha'],
                        ['name' => 'Embezzlement', 'name_sw' => 'Ubadhirifu'],
                        ['name' => 'False expense claims', 'name_sw' => 'Madai ya Udanganyifu ya Gharama'],
                        ['name' => 'Ghost beneficiaries', 'name_sw' => 'Wanufaika wa Udanganyifu'],
                    ],
                ],
                [
                    'key' => 'procurement_fraud',
                    'name' => 'Procurement Fraud',
                    'name_sw' => 'Ughushi wa Ununuzi',
                    'subcategories' => [
                        ['name' => 'Inflated pricing', 'name_sw' => 'Bei Zilizopandishwa'],
                        ['name' => 'Kickbacks', 'name_sw' => 'Rushwa'],
                        ['name' => 'Favoritism', 'name_sw' => 'Upendeleo'],
                        ['name' => 'Phantom suppliers', 'name_sw' => 'Wasambazaji wa Udanganyifu'],
                    ],
                ],
                [
                    'key' => 'program_mismanagement',
                    'name' => 'Program Mismanagement',
                    'name_sw' => 'Usimamizi Mbaya wa Mradi',
                    'subcategories' => [
                        ['name' => 'Falsified reports', 'name_sw' => 'Ripoti za Udanganyifu'],
                        ['name' => 'Deviation from objectives', 'name_sw' => 'Kutofuata Madhumuni'],
                        ['name' => 'Misrepresentation of impact', 'name_sw' => 'Udanganyifu wa Athari'],
                    ],
                ],
                [
                    'key' => 'workplace_misconduct',
                    'name' => 'Workplace Misconduct',
                    'name_sw' => 'Tabia Mbaya Kazini',
                    'subcategories' => [
                        ['name' => 'Sexual harassment', 'name_sw' => 'Udhalimu wa Kingono'],
                        ['name' => 'Discrimination', 'name_sw' => 'Ubaguzi'],
                        ['name' => 'Bullying', 'name_sw' => 'Uonevu'],
                        ['name' => 'Abuse of power', 'name_sw' => 'Matumizi Mabaya ya Mamlaka'],
                    ],
                ],
                [
                    'key' => 'beneficiary_harm',
                    'name' => 'Beneficiary Harm',
                    'name_sw' => 'Madhara kwa Wanufaika',
                    'subcategories' => [
                        ['name' => 'Exploitation of beneficiaries', 'name_sw' => 'Unyonyaji wa Wanufaika'],
                        ['name' => 'Sexual exploitation', 'name_sw' => 'Unyonyaji wa Kingono'],
                        ['name' => 'Safeguarding violations', 'name_sw' => 'Ukiukaji wa Ulinzi'],
                    ],
                ],
                [
                    'key' => 'compliance_violations',
                    'name' => 'Compliance Violations',
                    'name_sw' => 'Ukiukaji wa Kufuata',
                    'subcategories' => [
                        ['name' => 'Regulatory non-compliance', 'name_sw' => 'Kutofuata Kanuni'],
                        ['name' => 'Donor agreement breaches', 'name_sw' => 'Ukiukaji wa Mikataba ya Wafadhili'],
                        ['name' => 'Tax violations', 'name_sw' => 'Ukiukaji wa Kodi'],
                    ],
                ],
            ],

            // RELIGIOUS INSTITUTIONS SECTOR
            'religious_institutions' => [
                [
                    'key' => 'financial_mismanagement',
                    'name' => 'Financial Mismanagement',
                    'name_sw' => 'Usimamizi Mbaya wa Fedha',
                    'subcategories' => [
                        ['name' => 'Misuse of tithes/offerings', 'name_sw' => 'Matumizi Mabaya ya Zaka/Sadaka'],
                        ['name' => 'Embezzlement', 'name_sw' => 'Ubadhirifu'],
                        ['name' => 'Fraudulent fundraising', 'name_sw' => 'Ukusanyaji wa Fedha kwa Ughushi'],
                        ['name' => 'Lack of transparency', 'name_sw' => 'Ukosefu wa Uwazi'],
                    ],
                ],
                [
                    'key' => 'abuse_of_authority',
                    'name' => 'Abuse of Authority',
                    'name_sw' => 'Matumizi Mabaya ya Mamlaka',
                    'subcategories' => [
                        ['name' => 'Spiritual manipulation', 'name_sw' => 'Ughauzi wa Kiroho'],
                        ['name' => 'Exploitation of members', 'name_sw' => 'Unyonyaji wa Wanachama'],
                        ['name' => 'Coercion', 'name_sw' => 'Kulazimisha'],
                        ['name' => 'Intimidation', 'name_sw' => 'Kutisha'],
                    ],
                ],
                [
                    'key' => 'sexual_misconduct',
                    'name' => 'Sexual Misconduct',
                    'name_sw' => 'Tabia Mbaya za Kingono',
                    'subcategories' => [
                        ['name' => 'Sexual abuse of minors', 'name_sw' => 'Unyanyasaji wa Kingono wa Watoto'],
                        ['name' => 'Sexual harassment', 'name_sw' => 'Udhalimu wa Kingono'],
                        ['name' => 'Inappropriate relationships', 'name_sw' => 'Mahusiano Yasiyo ya Kawaida'],
                    ],
                ],
                [
                    'key' => 'child_safeguarding',
                    'name' => 'Child Safeguarding',
                    'name_sw' => 'Ulinzi wa Watoto',
                    'subcategories' => [
                        ['name' => 'Failure to protect children', 'name_sw' => 'Kushindwa Kulinda Watoto'],
                        ['name' => 'Physical abuse', 'name_sw' => 'Unyanyasaji wa Kimwili'],
                        ['name' => 'Emotional abuse', 'name_sw' => 'Unyanyasaji wa Kihisia'],
                        ['name' => 'Neglect', 'name_sw' => 'Upuuzi'],
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'name_sw' => 'Ubaguzi',
                    'subcategories' => [
                        ['name' => 'Gender discrimination', 'name_sw' => 'Ubaguzi wa Jinsia'],
                        ['name' => 'Ethnic discrimination', 'name_sw' => 'Ubaguzi wa Ukabila'],
                        ['name' => 'Social exclusion', 'name_sw' => 'Kuepukana Kijamii'],
                    ],
                ],
                [
                    'key' => 'property_misuse',
                    'name' => 'Property Misuse',
                    'name_sw' => 'Matumizi Mabaya ya Mali',
                    'subcategories' => [
                        ['name' => 'Illegal sale of property', 'name_sw' => 'Uuzaji wa Mali kwa Ufisadi'],
                        ['name' => 'Personal use of church assets', 'name_sw' => 'Matumizi Binafsi ya Mali ya Kanisa'],
                        ['name' => 'Land grabbing', 'name_sw' => 'Unyang\'anyaji Ardhi'],
                    ],
                ],
            ],

            // TRANSPORT & LOGISTICS SECTOR
            'transport_logistics' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'name_sw' => 'Ukiukaji wa Usalama',
                    'subcategories' => [
                        ['name' => 'Overloading', 'name_sw' => 'Kupakia Kupita Kiasi'],
                        ['name' => 'Unsafe vehicles', 'name_sw' => 'Magari Yasiyosalama'],
                        ['name' => 'Driver fatigue', 'name_sw' => 'Uchovu wa Madereva'],
                        ['name' => 'Speeding', 'name_sw' => 'Mwendo wa Kasi'],
                    ],
                ],
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'name_sw' => 'Ughushi',
                    'subcategories' => [
                        ['name' => 'Cargo theft', 'name_sw' => 'Wizi wa Mizigo'],
                        ['name' => 'Fuel theft', 'name_sw' => 'Wizi wa Mafuta'],
                        ['name' => 'Document falsification', 'name_sw' => 'Udanganyifu wa Nyaraka'],
                        ['name' => 'Customs fraud', 'name_sw' => 'Ughushi wa Forodha'],
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'name_sw' => 'Ukiukaji wa Haki za Wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Excessive working hours', 'name_sw' => 'Masaa ya Kazi Kupita Kiasi'],
                        ['name' => 'Wage theft', 'name_sw' => 'Wizi wa Mishahara'],
                        ['name' => 'Poor working conditions', 'name_sw' => 'Mazingira Mabaya ya Kazi'],
                        ['name' => 'Denial of benefits', 'name_sw' => 'Kukataliwa Faida'],
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'name_sw' => 'Ukiukaji wa Mazingira',
                    'subcategories' => [
                        ['name' => 'Emissions violations', 'name_sw' => 'Ukiukaji wa Uzalishaji wa Hewa'],
                        ['name' => 'Illegal dumping', 'name_sw' => 'Kutupa Taka kwa Ufisadi'],
                        ['name' => 'Fuel spills', 'name_sw' => 'Umwagikaji wa Mafuta'],
                    ],
                ],
                [
                    'key' => 'regulatory_violations',
                    'name' => 'Regulatory Violations',
                    'name_sw' => 'Ukiukaji wa Kanuni',
                    'subcategories' => [
                        ['name' => 'Operating without licenses', 'name_sw' => 'Kufanya Kazi bila Leseni'],
                        ['name' => 'Insurance violations', 'name_sw' => 'Ukiukaji wa Bima'],
                        ['name' => 'Route violations', 'name_sw' => 'Ukiukaji wa Njia'],
                    ],
                ],
                [
                    'key' => 'customer_harm',
                    'name' => 'Customer Harm',
                    'name_sw' => 'Madhara kwa Wateja',
                    'subcategories' => [
                        ['name' => 'Overcharging', 'name_sw' => 'Kulipisha Zaidi'],
                        ['name' => 'Loss of goods', 'name_sw' => 'Kupoteza Bidhaa'],
                        ['name' => 'Poor service', 'name_sw' => 'Huduma Mbaya'],
                        ['name' => 'Harassment of passengers', 'name_sw' => 'Udhalimu wa Abiria'],
                    ],
                ],
            ],
        ];
    }
}
