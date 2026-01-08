<?php

namespace Database\Seeders;

use App\Models\SectorFeedbackTemplate;
use Illuminate\Database\Seeder;

class SectorFeedbackTemplateSeederWithSwahili extends Seeder
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
                SectorFeedbackTemplate::updateOrCreate(
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
                    SectorFeedbackTemplate::updateOrCreate(
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
                    'key' => 'teaching_quality',
                    'name' => 'Teaching Quality',
                    'name_sw' => 'Ubora wa Ufundishaji',
                    'description' => 'Feedback related to teaching and instruction quality',
                    'description_sw' => 'Maoni yanayohusu ubora wa ufundishaji na maelekezo',
                    'subcategories' => [
                        ['name' => 'Course content relevance', 'name_sw' => 'Umuhimu wa maudhui ya kozi'],
                        ['name' => 'Teaching methods effectiveness', 'name_sw' => 'Ufanisi wa njia za ufundishaji'],
                        ['name' => 'Instructor engagement', 'name_sw' => 'Ushirikishwaji wa mwalimu'],
                        ['name' => 'Assessment fairness', 'name_sw' => 'Usawa wa tathmini'],
                        ['name' => 'Learning materials quality', 'name_sw' => 'Ubora wa vifaa vya kujifunzia'],
                    ],
                ],
                [
                    'key' => 'facilities_resources',
                    'name' => 'Facilities & Resources',
                    'name_sw' => 'Miundombinu na Rasilimali',
                    'description' => 'Feedback about campus facilities and learning resources',
                    'description_sw' => 'Maoni kuhusu miundombinu ya kampasi na rasilimali za kujifunzia',
                    'subcategories' => [
                        ['name' => 'Classroom conditions', 'name_sw' => 'Hali za madarasa'],
                        ['name' => 'Library services', 'name_sw' => 'Huduma za maktaba'],
                        ['name' => 'Laboratory equipment', 'name_sw' => 'Vifaa vya maabara'],
                        ['name' => 'IT infrastructure', 'name_sw' => 'Miundombinu ya TEHAMA'],
                        ['name' => 'Sports facilities', 'name_sw' => 'Miundombinu ya michezo'],
                    ],
                ],
                [
                    'key' => 'student_services',
                    'name' => 'Student Services',
                    'name_sw' => 'Huduma za Wanafunzi',
                    'description' => 'Feedback on student support services',
                    'description_sw' => 'Maoni kuhusu huduma za msaada kwa wanafunzi',
                    'subcategories' => [
                        ['name' => 'Counseling services', 'name_sw' => 'Huduma za ushauri'],
                        ['name' => 'Career guidance', 'name_sw' => 'Mwongozo wa kazi'],
                        ['name' => 'Admissions process', 'name_sw' => 'Mchakato wa kuingizwa'],
                        ['name' => 'Financial aid support', 'name_sw' => 'Msaada wa kifedha'],
                        ['name' => 'Health services', 'name_sw' => 'Huduma za afya'],
                    ],
                ],
                [
                    'key' => 'campus_environment',
                    'name' => 'Campus Environment',
                    'name_sw' => 'Mazingira ya Kampasi',
                    'description' => 'Feedback about the overall campus atmosphere',
                    'description_sw' => 'Maoni kuhusu mazingira ya jumla ya kampasi',
                    'subcategories' => [
                        ['name' => 'Safety and security', 'name_sw' => 'Usalama na ulinzi'],
                        ['name' => 'Cleanliness', 'name_sw' => 'Usafi'],
                        ['name' => 'Cafeteria services', 'name_sw' => 'Huduma za mkahawa'],
                        ['name' => 'Transportation', 'name_sw' => 'Usafiri'],
                        ['name' => 'Student activities', 'name_sw' => 'Shughuli za wanafunzi'],
                    ],
                ],
            ],

            // CORPORATE/WORKPLACE SECTOR
            'corporate_workplace' => [
                [
                    'key' => 'work_environment',
                    'name' => 'Work Environment',
                    'name_sw' => 'Mazingira ya Kazi',
                    'description' => 'Feedback about workplace conditions',
                    'description_sw' => 'Maoni kuhusu hali za mahali pa kazi',
                    'subcategories' => [
                        ['name' => 'Office facilities', 'name_sw' => 'Miundombinu ya ofisi'],
                        ['name' => 'Work-life balance', 'name_sw' => 'Usawa wa kazi na maisha'],
                        ['name' => 'Team collaboration', 'name_sw' => 'Ushirikiano wa timu'],
                        ['name' => 'Remote work support', 'name_sw' => 'Msaada wa kazi ya mbali'],
                        ['name' => 'Workplace safety', 'name_sw' => 'Usalama wa mahali pa kazi'],
                    ],
                ],
                [
                    'key' => 'management_leadership',
                    'name' => 'Management & Leadership',
                    'name_sw' => 'Usimamizi na Uongozi',
                    'description' => 'Feedback about management practices',
                    'description_sw' => 'Maoni kuhusu mazoea ya usimamizi',
                    'subcategories' => [
                        ['name' => 'Communication from leadership', 'name_sw' => 'Mawasiliano kutoka kwa viongozi'],
                        ['name' => 'Decision-making transparency', 'name_sw' => 'Uwazi wa maamuzi'],
                        ['name' => 'Performance management', 'name_sw' => 'Usimamizi wa utendaji'],
                        ['name' => 'Recognition programs', 'name_sw' => 'Programu za utambuzi'],
                        ['name' => 'Career development opportunities', 'name_sw' => 'Fursa za maendeleo ya kazi'],
                    ],
                ],
                [
                    'key' => 'compensation_benefits',
                    'name' => 'Compensation & Benefits',
                    'name_sw' => 'Malipo na Manufaa',
                    'description' => 'Feedback about pay and employee benefits',
                    'description_sw' => 'Maoni kuhusu malipo na manufaa ya wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Salary competitiveness', 'name_sw' => 'Ushindani wa mshahara'],
                        ['name' => 'Bonus structure', 'name_sw' => 'Muundo wa bonasi'],
                        ['name' => 'Health insurance', 'name_sw' => 'Bima ya afya'],
                        ['name' => 'Retirement benefits', 'name_sw' => 'Manufaa ya kustaafu'],
                        ['name' => 'Leave policies', 'name_sw' => 'Sera za likizo'],
                    ],
                ],
                [
                    'key' => 'professional_development',
                    'name' => 'Professional Development',
                    'name_sw' => 'Maendeleo ya Kitaaluma',
                    'description' => 'Feedback about learning and growth opportunities',
                    'description_sw' => 'Maoni kuhusu fursa za kujifunza na kukua',
                    'subcategories' => [
                        ['name' => 'Training programs', 'name_sw' => 'Programu za mafunzo'],
                        ['name' => 'Skill development', 'name_sw' => 'Maendeleo ya ujuzi'],
                        ['name' => 'Mentorship programs', 'name_sw' => 'Programu za ushauri'],
                        ['name' => 'Conference attendance', 'name_sw' => 'Kuhudhuria mikutano'],
                        ['name' => 'Certification support', 'name_sw' => 'Msaada wa vyeti'],
                    ],
                ],
            ],

            // FINANCIAL & INSURANCE SECTOR
            'financial_insurance' => [
                [
                    'key' => 'customer_service',
                    'name' => 'Customer Service',
                    'name_sw' => 'Huduma kwa Wateja',
                    'description' => 'Feedback about customer-facing services',
                    'description_sw' => 'Maoni kuhusu huduma zinazohusiana na wateja',
                    'subcategories' => [
                        ['name' => 'Response time', 'name_sw' => 'Muda wa majibu'],
                        ['name' => 'Staff knowledge', 'name_sw' => 'Ujuzi wa wafanyakazi'],
                        ['name' => 'Problem resolution', 'name_sw' => 'Utatuzi wa matatizo'],
                        ['name' => 'Communication clarity', 'name_sw' => 'Uwazi wa mawasiliano'],
                        ['name' => 'Service accessibility', 'name_sw' => 'Upatikanaji wa huduma'],
                    ],
                ],
                [
                    'key' => 'products_services',
                    'name' => 'Products & Services',
                    'name_sw' => 'Bidhaa na Huduma',
                    'description' => 'Feedback about financial products and services',
                    'description_sw' => 'Maoni kuhusu bidhaa na huduma za kifedha',
                    'subcategories' => [
                        ['name' => 'Product range', 'name_sw' => 'Aina za bidhaa'],
                        ['name' => 'Pricing transparency', 'name_sw' => 'Uwazi wa bei'],
                        ['name' => 'Terms and conditions clarity', 'name_sw' => 'Uwazi wa masharti'],
                        ['name' => 'Digital services', 'name_sw' => 'Huduma za kidijitali'],
                        ['name' => 'Product innovation', 'name_sw' => 'Ubunifu wa bidhaa'],
                    ],
                ],
                [
                    'key' => 'branch_experience',
                    'name' => 'Branch Experience',
                    'name_sw' => 'Uzoefu wa Tawi',
                    'description' => 'Feedback about physical branch services',
                    'description_sw' => 'Maoni kuhusu huduma za tawi la kimwili',
                    'subcategories' => [
                        ['name' => 'Wait times', 'name_sw' => 'Muda wa kusubiri'],
                        ['name' => 'Branch facilities', 'name_sw' => 'Miundombinu ya tawi'],
                        ['name' => 'ATM availability', 'name_sw' => 'Upatikanaji wa ATM'],
                        ['name' => 'Branch locations', 'name_sw' => 'Maeneo ya matawi'],
                        ['name' => 'Operating hours', 'name_sw' => 'Masaa ya kufanya kazi'],
                    ],
                ],
                [
                    'key' => 'digital_banking',
                    'name' => 'Digital Banking',
                    'name_sw' => 'Benki ya Kidijitali',
                    'description' => 'Feedback about online and mobile services',
                    'description_sw' => 'Maoni kuhusu huduma za mtandaoni na simu',
                    'subcategories' => [
                        ['name' => 'Mobile app usability', 'name_sw' => 'Urahisi wa matumizi ya programu'],
                        ['name' => 'Online platform features', 'name_sw' => 'Vipengele vya jukwaa la mtandaoni'],
                        ['name' => 'Transaction security', 'name_sw' => 'Usalama wa miamala'],
                        ['name' => 'System reliability', 'name_sw' => 'Kuaminika kwa mfumo'],
                        ['name' => 'Digital onboarding', 'name_sw' => 'Usajili wa kidijitali'],
                    ],
                ],
            ],

            // HEALTHCARE SECTOR
            'healthcare' => [
                [
                    'key' => 'patient_care',
                    'name' => 'Patient Care',
                    'name_sw' => 'Huduma kwa Wagonjwa',
                    'description' => 'Feedback about quality of care received',
                    'description_sw' => 'Maoni kuhusu ubora wa huduma iliyopokelewa',
                    'subcategories' => [
                        ['name' => 'Medical staff competence', 'name_sw' => 'Ujuzi wa wafanyakazi wa kitiba'],
                        ['name' => 'Nursing care quality', 'name_sw' => 'Ubora wa huduma za uuguzi'],
                        ['name' => 'Treatment effectiveness', 'name_sw' => 'Ufanisi wa matibabu'],
                        ['name' => 'Pain management', 'name_sw' => 'Usimamizi wa maumivu'],
                        ['name' => 'Care coordination', 'name_sw' => 'Uratibu wa huduma'],
                    ],
                ],
                [
                    'key' => 'facilities_equipment',
                    'name' => 'Facilities & Equipment',
                    'name_sw' => 'Miundombinu na Vifaa',
                    'description' => 'Feedback about healthcare facilities',
                    'description_sw' => 'Maoni kuhusu miundombinu ya afya',
                    'subcategories' => [
                        ['name' => 'Facility cleanliness', 'name_sw' => 'Usafi wa kituo'],
                        ['name' => 'Room comfort', 'name_sw' => 'Starehe ya chumba'],
                        ['name' => 'Medical equipment', 'name_sw' => 'Vifaa vya kitiba'],
                        ['name' => 'Diagnostic facilities', 'name_sw' => 'Miundombinu ya uchunguzi'],
                        ['name' => 'Parking availability', 'name_sw' => 'Upatikanaji wa maeneo ya kuegesha magari'],
                    ],
                ],
                [
                    'key' => 'administrative_services',
                    'name' => 'Administrative Services',
                    'name_sw' => 'Huduma za Utawala',
                    'description' => 'Feedback about administrative processes',
                    'description_sw' => 'Maoni kuhusu michakato ya utawala',
                    'subcategories' => [
                        ['name' => 'Appointment scheduling', 'name_sw' => 'Kupanga miadi'],
                        ['name' => 'Billing transparency', 'name_sw' => 'Uwazi wa malipo'],
                        ['name' => 'Insurance processing', 'name_sw' => 'Usindikaji wa bima'],
                        ['name' => 'Medical records access', 'name_sw' => 'Upatikanaji wa rekodi za matibabu'],
                        ['name' => 'Wait times', 'name_sw' => 'Muda wa kusubiri'],
                    ],
                ],
                [
                    'key' => 'communication',
                    'name' => 'Communication',
                    'name_sw' => 'Mawasiliano',
                    'description' => 'Feedback about healthcare communication',
                    'description_sw' => 'Maoni kuhusu mawasiliano ya afya',
                    'subcategories' => [
                        ['name' => 'Doctor-patient communication', 'name_sw' => 'Mawasiliano ya daktari na mgonjwa'],
                        ['name' => 'Test result delivery', 'name_sw' => 'Utoaji wa matokeo ya uchunguzi'],
                        ['name' => 'Discharge instructions', 'name_sw' => 'Maelekezo ya kutoka hospitalini'],
                        ['name' => 'Follow-up communication', 'name_sw' => 'Mawasiliano ya kufuatilia'],
                        ['name' => 'Health education', 'name_sw' => 'Elimu ya afya'],
                    ],
                ],
            ],

            // MANUFACTURING & INDUSTRIAL SECTOR
            'manufacturing_industrial' => [
                [
                    'key' => 'workplace_safety',
                    'name' => 'Workplace Safety',
                    'name_sw' => 'Usalama wa Kazini',
                    'description' => 'Feedback about safety practices and conditions',
                    'description_sw' => 'Maoni kuhusu mazoea na hali za usalama',
                    'subcategories' => [
                        ['name' => 'Safety equipment availability', 'name_sw' => 'Upatikanaji wa vifaa vya usalama'],
                        ['name' => 'Safety training adequacy', 'name_sw' => 'Utoshelezi wa mafunzo ya usalama'],
                        ['name' => 'Hazard identification', 'name_sw' => 'Utambulisho wa hatari'],
                        ['name' => 'Emergency procedures', 'name_sw' => 'Taratibu za dharura'],
                        ['name' => 'Incident response', 'name_sw' => 'Majibu ya matukio'],
                    ],
                ],
                [
                    'key' => 'production_processes',
                    'name' => 'Production Processes',
                    'name_sw' => 'Michakato ya Uzalishaji',
                    'description' => 'Feedback about manufacturing operations',
                    'description_sw' => 'Maoni kuhusu shughuli za utengenezaji',
                    'subcategories' => [
                        ['name' => 'Process efficiency', 'name_sw' => 'Ufanisi wa michakato'],
                        ['name' => 'Quality control', 'name_sw' => 'Udhibiti wa ubora'],
                        ['name' => 'Equipment maintenance', 'name_sw' => 'Matengenezo ya vifaa'],
                        ['name' => 'Production scheduling', 'name_sw' => 'Upangaji wa uzalishaji'],
                        ['name' => 'Resource allocation', 'name_sw' => 'Ugawaji wa rasilimali'],
                    ],
                ],
                [
                    'key' => 'working_conditions',
                    'name' => 'Working Conditions',
                    'name_sw' => 'Hali za Kazi',
                    'description' => 'Feedback about factory floor conditions',
                    'description_sw' => 'Maoni kuhusu hali za chumba cha kiwanda',
                    'subcategories' => [
                        ['name' => 'Temperature control', 'name_sw' => 'Udhibiti wa joto'],
                        ['name' => 'Noise levels', 'name_sw' => 'Viwango vya kelele'],
                        ['name' => 'Break facilities', 'name_sw' => 'Miundombinu ya mapumziko'],
                        ['name' => 'Shift scheduling', 'name_sw' => 'Upangaji wa zamu'],
                        ['name' => 'Physical workload', 'name_sw' => 'Mzigo wa kazi wa kimwili'],
                    ],
                ],
                [
                    'key' => 'employee_relations',
                    'name' => 'Employee Relations',
                    'name_sw' => 'Mahusiano ya Wafanyakazi',
                    'description' => 'Feedback about management-worker relations',
                    'description_sw' => 'Maoni kuhusu mahusiano ya usimamizi na wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Supervisor communication', 'name_sw' => 'Mawasiliano ya msimamizi'],
                        ['name' => 'Union relations', 'name_sw' => 'Mahusiano ya chama'],
                        ['name' => 'Grievance handling', 'name_sw' => 'Kushughulikia malalamiko'],
                        ['name' => 'Team dynamics', 'name_sw' => 'Mienendo ya timu'],
                        ['name' => 'Recognition programs', 'name_sw' => 'Programu za utambuzi'],
                    ],
                ],
            ],

            // CONSTRUCTION & ENGINEERING SECTOR
            'construction_engineering' => [
                [
                    'key' => 'site_safety',
                    'name' => 'Site Safety',
                    'name_sw' => 'Usalama wa Eneo la Ujenzi',
                    'description' => 'Feedback about construction site safety',
                    'description_sw' => 'Maoni kuhusu usalama wa eneo la ujenzi',
                    'subcategories' => [
                        ['name' => 'PPE provision', 'name_sw' => 'Utoaji wa vifaa vya kujikinga'],
                        ['name' => 'Safety briefings', 'name_sw' => 'Maelezo ya usalama'],
                        ['name' => 'Site access control', 'name_sw' => 'Udhibiti wa kuingia eneo'],
                        ['name' => 'Scaffolding safety', 'name_sw' => 'Usalama wa scaffolding'],
                        ['name' => 'Heavy equipment handling', 'name_sw' => 'Kushughulikia vifaa vizito'],
                    ],
                ],
                [
                    'key' => 'project_management',
                    'name' => 'Project Management',
                    'name_sw' => 'Usimamizi wa Miradi',
                    'description' => 'Feedback about project execution',
                    'description_sw' => 'Maoni kuhusu utekelezaji wa miradi',
                    'subcategories' => [
                        ['name' => 'Timeline management', 'name_sw' => 'Usimamizi wa ratiba'],
                        ['name' => 'Resource coordination', 'name_sw' => 'Uratibu wa rasilimali'],
                        ['name' => 'Communication with stakeholders', 'name_sw' => 'Mawasiliano na wadau'],
                        ['name' => 'Change order handling', 'name_sw' => 'Kushughulikia mabadiliko'],
                        ['name' => 'Budget management', 'name_sw' => 'Usimamizi wa bajeti'],
                    ],
                ],
                [
                    'key' => 'quality_standards',
                    'name' => 'Quality Standards',
                    'name_sw' => 'Viwango vya Ubora',
                    'description' => 'Feedback about construction quality',
                    'description_sw' => 'Maoni kuhusu ubora wa ujenzi',
                    'subcategories' => [
                        ['name' => 'Material quality', 'name_sw' => 'Ubora wa vifaa'],
                        ['name' => 'Workmanship standards', 'name_sw' => 'Viwango vya ufundi'],
                        ['name' => 'Inspection processes', 'name_sw' => 'Michakato ya ukaguzi'],
                        ['name' => 'Compliance adherence', 'name_sw' => 'Kuzingatia sheria'],
                        ['name' => 'Defect rectification', 'name_sw' => 'Kurekebisha kasoro'],
                    ],
                ],
                [
                    'key' => 'workforce_conditions',
                    'name' => 'Workforce Conditions',
                    'name_sw' => 'Hali za Wafanyakazi',
                    'description' => 'Feedback about worker welfare',
                    'description_sw' => 'Maoni kuhusu ustawi wa wafanyakazi',
                    'subcategories' => [
                        ['name' => 'Wage payment timeliness', 'name_sw' => 'Ulipaji wa ujira kwa wakati'],
                        ['name' => 'Accommodation quality', 'name_sw' => 'Ubora wa makazi'],
                        ['name' => 'Rest periods', 'name_sw' => 'Muda wa kupumzika'],
                        ['name' => 'Training opportunities', 'name_sw' => 'Fursa za mafunzo'],
                        ['name' => 'Contract terms clarity', 'name_sw' => 'Uwazi wa masharti ya mkataba'],
                    ],
                ],
            ],

            // SECURITY & UNIFORMED SERVICES SECTOR
            'security_uniformed_services' => [
                [
                    'key' => 'service_delivery',
                    'name' => 'Service Delivery',
                    'name_sw' => 'Utoaji wa Huduma',
                    'description' => 'Feedback about security service quality',
                    'description_sw' => 'Maoni kuhusu ubora wa huduma za ulinzi',
                    'subcategories' => [
                        ['name' => 'Response time', 'name_sw' => 'Muda wa majibu'],
                        ['name' => 'Patrol effectiveness', 'name_sw' => 'Ufanisi wa doria'],
                        ['name' => 'Incident handling', 'name_sw' => 'Kushughulikia matukio'],
                        ['name' => 'Client communication', 'name_sw' => 'Mawasiliano na mteja'],
                        ['name' => 'Report accuracy', 'name_sw' => 'Usahihi wa ripoti'],
                    ],
                ],
                [
                    'key' => 'personnel_conduct',
                    'name' => 'Personnel Conduct',
                    'name_sw' => 'Tabia ya Wafanyakazi',
                    'description' => 'Feedback about security staff behavior',
                    'description_sw' => 'Maoni kuhusu tabia ya wafanyakazi wa ulinzi',
                    'subcategories' => [
                        ['name' => 'Professionalism', 'name_sw' => 'Utaalamu'],
                        ['name' => 'Appearance standards', 'name_sw' => 'Viwango vya mwonekano'],
                        ['name' => 'Customer interaction', 'name_sw' => 'Mwingiliano na wateja'],
                        ['name' => 'Protocol adherence', 'name_sw' => 'Kufuata taratibu'],
                        ['name' => 'Conflict resolution', 'name_sw' => 'Utatuzi wa migogoro'],
                    ],
                ],
                [
                    'key' => 'equipment_resources',
                    'name' => 'Equipment & Resources',
                    'name_sw' => 'Vifaa na Rasilimali',
                    'description' => 'Feedback about security equipment',
                    'description_sw' => 'Maoni kuhusu vifaa vya ulinzi',
                    'subcategories' => [
                        ['name' => 'Uniform quality', 'name_sw' => 'Ubora wa sare'],
                        ['name' => 'Communication devices', 'name_sw' => 'Vifaa vya mawasiliano'],
                        ['name' => 'Security technology', 'name_sw' => 'Teknolojia ya ulinzi'],
                        ['name' => 'Vehicle maintenance', 'name_sw' => 'Matengenezo ya magari'],
                        ['name' => 'Control room facilities', 'name_sw' => 'Miundombinu ya chumba cha udhibiti'],
                    ],
                ],
                [
                    'key' => 'training_development',
                    'name' => 'Training & Development',
                    'name_sw' => 'Mafunzo na Maendeleo',
                    'description' => 'Feedback about security training',
                    'description_sw' => 'Maoni kuhusu mafunzo ya ulinzi',
                    'subcategories' => [
                        ['name' => 'Initial training quality', 'name_sw' => 'Ubora wa mafunzo ya awali'],
                        ['name' => 'Ongoing training', 'name_sw' => 'Mafunzo ya kuendelea'],
                        ['name' => 'Certification programs', 'name_sw' => 'Programu za vyeti'],
                        ['name' => 'Skills assessment', 'name_sw' => 'Tathmini ya ujuzi'],
                        ['name' => 'Career progression', 'name_sw' => 'Maendeleo ya kazi'],
                    ],
                ],
            ],

            // HOSPITALITY, TRAVEL & TOURISM SECTOR
            'hospitality_travel_tourism' => [
                [
                    'key' => 'guest_experience',
                    'name' => 'Guest Experience',
                    'name_sw' => 'Uzoefu wa Mgeni',
                    'description' => 'Feedback about customer experience',
                    'description_sw' => 'Maoni kuhusu uzoefu wa mteja',
                    'subcategories' => [
                        ['name' => 'Check-in/check-out process', 'name_sw' => 'Mchakato wa kuingia na kutoka'],
                        ['name' => 'Room quality', 'name_sw' => 'Ubora wa chumba'],
                        ['name' => 'Staff friendliness', 'name_sw' => 'Urafiki wa wafanyakazi'],
                        ['name' => 'Service responsiveness', 'name_sw' => 'Majibu ya haraka ya huduma'],
                        ['name' => 'Special requests handling', 'name_sw' => 'Kushughulikia maombi maalum'],
                    ],
                ],
                [
                    'key' => 'food_beverage',
                    'name' => 'Food & Beverage',
                    'name_sw' => 'Chakula na Vinywaji',
                    'description' => 'Feedback about dining services',
                    'description_sw' => 'Maoni kuhusu huduma za ulaji',
                    'subcategories' => [
                        ['name' => 'Food quality', 'name_sw' => 'Ubora wa chakula'],
                        ['name' => 'Menu variety', 'name_sw' => 'Aina za menyu'],
                        ['name' => 'Service speed', 'name_sw' => 'Kasi ya huduma'],
                        ['name' => 'Dietary accommodations', 'name_sw' => 'Marekebisho ya mlo'],
                        ['name' => 'Presentation standards', 'name_sw' => 'Viwango vya uwasilishaji'],
                    ],
                ],
                [
                    'key' => 'facilities_amenities',
                    'name' => 'Facilities & Amenities',
                    'name_sw' => 'Miundombinu na Huduma',
                    'description' => 'Feedback about hotel/resort facilities',
                    'description_sw' => 'Maoni kuhusu miundombinu ya hoteli',
                    'subcategories' => [
                        ['name' => 'Pool and spa', 'name_sw' => 'Bwawa na spa'],
                        ['name' => 'Fitness center', 'name_sw' => 'Kituo cha mazoezi'],
                        ['name' => 'Business center', 'name_sw' => 'Kituo cha biashara'],
                        ['name' => 'Wifi connectivity', 'name_sw' => 'Muunganisho wa Wifi'],
                        ['name' => 'Entertainment facilities', 'name_sw' => 'Miundombinu ya burudani'],
                    ],
                ],
                [
                    'key' => 'cleanliness_hygiene',
                    'name' => 'Cleanliness & Hygiene',
                    'name_sw' => 'Usafi na Usafi wa Afya',
                    'description' => 'Feedback about cleanliness standards',
                    'description_sw' => 'Maoni kuhusu viwango vya usafi',
                    'subcategories' => [
                        ['name' => 'Room cleanliness', 'name_sw' => 'Usafi wa chumba'],
                        ['name' => 'Public area maintenance', 'name_sw' => 'Matengenezo ya maeneo ya umma'],
                        ['name' => 'Bathroom hygiene', 'name_sw' => 'Usafi wa bafu'],
                        ['name' => 'Linen quality', 'name_sw' => 'Ubora wa mashuka'],
                        ['name' => 'COVID-19 protocols', 'name_sw' => 'Taratibu za COVID-19'],
                    ],
                ],
            ],

            // NGO/CSO/DONOR FUNDED SECTOR
            'ngo_cso_donor_funded' => [
                [
                    'key' => 'program_effectiveness',
                    'name' => 'Program Effectiveness',
                    'name_sw' => 'Ufanisi wa Programu',
                    'description' => 'Feedback about program impact',
                    'description_sw' => 'Maoni kuhusu athari ya programu',
                    'subcategories' => [
                        ['name' => 'Beneficiary reach', 'name_sw' => 'Ufikio wa wanufaika'],
                        ['name' => 'Program relevance', 'name_sw' => 'Umuhimu wa programu'],
                        ['name' => 'Outcome achievement', 'name_sw' => 'Upatikanaji wa matokeo'],
                        ['name' => 'Community engagement', 'name_sw' => 'Ushiriki wa jamii'],
                        ['name' => 'Sustainability measures', 'name_sw' => 'Hatua za kudumu'],
                    ],
                ],
                [
                    'key' => 'donor_relations',
                    'name' => 'Donor Relations',
                    'name_sw' => 'Mahusiano ya Wafadhili',
                    'description' => 'Feedback about donor engagement',
                    'description_sw' => 'Maoni kuhusu ushirikiano na wafadhili',
                    'subcategories' => [
                        ['name' => 'Reporting quality', 'name_sw' => 'Ubora wa ripoti'],
                        ['name' => 'Communication frequency', 'name_sw' => 'Mzunguko wa mawasiliano'],
                        ['name' => 'Fund utilization transparency', 'name_sw' => 'Uwazi wa matumizi ya fedha'],
                        ['name' => 'Impact documentation', 'name_sw' => 'Uandishi wa athari'],
                        ['name' => 'Partnership management', 'name_sw' => 'Usimamizi wa ushirikiano'],
                    ],
                ],
                [
                    'key' => 'organizational_culture',
                    'name' => 'Organizational Culture',
                    'name_sw' => 'Utamaduni wa Shirika',
                    'description' => 'Feedback about workplace culture',
                    'description_sw' => 'Maoni kuhusu utamaduni wa mahali pa kazi',
                    'subcategories' => [
                        ['name' => 'Values alignment', 'name_sw' => 'Ulandanishaji wa maadili'],
                        ['name' => 'Staff wellbeing', 'name_sw' => 'Ustawi wa wafanyakazi'],
                        ['name' => 'Volunteer management', 'name_sw' => 'Usimamizi wa wajitoleaji'],
                        ['name' => 'Diversity and inclusion', 'name_sw' => 'Utofauti na ujumuishaji'],
                        ['name' => 'Work-life balance', 'name_sw' => 'Usawa wa kazi na maisha'],
                    ],
                ],
                [
                    'key' => 'governance',
                    'name' => 'Governance',
                    'name_sw' => 'Utawala',
                    'description' => 'Feedback about organizational governance',
                    'description_sw' => 'Maoni kuhusu utawala wa shirika',
                    'subcategories' => [
                        ['name' => 'Decision-making processes', 'name_sw' => 'Michakato ya maamuzi'],
                        ['name' => 'Policy adherence', 'name_sw' => 'Kufuata sera'],
                        ['name' => 'Accountability measures', 'name_sw' => 'Hatua za uwajibikaji'],
                        ['name' => 'Board effectiveness', 'name_sw' => 'Ufanisi wa bodi'],
                        ['name' => 'Conflict of interest management', 'name_sw' => 'Usimamizi wa migogoro ya maslahi'],
                    ],
                ],
            ],

            // RELIGIOUS INSTITUTIONS SECTOR
            'religious_institutions' => [
                [
                    'key' => 'spiritual_programs',
                    'name' => 'Spiritual Programs',
                    'name_sw' => 'Programu za Kiroho',
                    'description' => 'Feedback about religious services and programs',
                    'description_sw' => 'Maoni kuhusu huduma na programu za kidini',
                    'subcategories' => [
                        ['name' => 'Service quality', 'name_sw' => 'Ubora wa huduma'],
                        ['name' => 'Sermon relevance', 'name_sw' => 'Umuhimu wa mahubiri'],
                        ['name' => 'Music and worship', 'name_sw' => 'Muziki na ibada'],
                        ['name' => 'Youth programs', 'name_sw' => 'Programu za vijana'],
                        ['name' => 'Adult education', 'name_sw' => 'Elimu ya watu wazima'],
                    ],
                ],
                [
                    'key' => 'community_engagement',
                    'name' => 'Community Engagement',
                    'name_sw' => 'Ushiriki wa Jamii',
                    'description' => 'Feedback about community involvement',
                    'description_sw' => 'Maoni kuhusu ushiriki wa jamii',
                    'subcategories' => [
                        ['name' => 'Outreach programs', 'name_sw' => 'Programu za kufika jamii'],
                        ['name' => 'Fellowship activities', 'name_sw' => 'Shughuli za ushirika'],
                        ['name' => 'Community support', 'name_sw' => 'Msaada wa jamii'],
                        ['name' => 'Social events', 'name_sw' => 'Matukio ya kijamii'],
                        ['name' => 'Volunteer opportunities', 'name_sw' => 'Fursa za kujitolea'],
                    ],
                ],
                [
                    'key' => 'facilities_services',
                    'name' => 'Facilities & Services',
                    'name_sw' => 'Miundombinu na Huduma',
                    'description' => 'Feedback about institution facilities',
                    'description_sw' => 'Maoni kuhusu miundombinu ya taasisi',
                    'subcategories' => [
                        ['name' => 'Worship space', 'name_sw' => 'Nafasi ya ibada'],
                        ['name' => 'Meeting rooms', 'name_sw' => 'Vyumba vya mikutano'],
                        ['name' => 'Parking facilities', 'name_sw' => 'Maeneo ya kuegesha magari'],
                        ['name' => 'Accessibility', 'name_sw' => 'Upatikanaji'],
                        ['name' => 'Childcare services', 'name_sw' => 'Huduma za malezi ya watoto'],
                    ],
                ],
                [
                    'key' => 'pastoral_care',
                    'name' => 'Pastoral Care',
                    'name_sw' => 'Huduma za Kiroho',
                    'description' => 'Feedback about spiritual guidance',
                    'description_sw' => 'Maoni kuhusu mwongozo wa kiroho',
                    'subcategories' => [
                        ['name' => 'Counseling availability', 'name_sw' => 'Upatikanaji wa ushauri'],
                        ['name' => 'Hospital visits', 'name_sw' => 'Ziara za hospitalini'],
                        ['name' => 'Marriage guidance', 'name_sw' => 'Mwongozo wa ndoa'],
                        ['name' => 'Grief support', 'name_sw' => 'Msaada wa huzuni'],
                        ['name' => 'Crisis intervention', 'name_sw' => 'Kuingilia kati wakati wa dharura'],
                    ],
                ],
            ],

            // TRANSPORT & LOGISTICS SECTOR
            'transport_logistics' => [
                [
                    'key' => 'service_reliability',
                    'name' => 'Service Reliability',
                    'name_sw' => 'Kuaminika kwa Huduma',
                    'description' => 'Feedback about transport service reliability',
                    'description_sw' => 'Maoni kuhusu uaminifu wa huduma za usafiri',
                    'subcategories' => [
                        ['name' => 'On-time performance', 'name_sw' => 'Utendaji wa kwa wakati'],
                        ['name' => 'Route coverage', 'name_sw' => 'Ufikio wa njia'],
                        ['name' => 'Schedule adherence', 'name_sw' => 'Kufuata ratiba'],
                        ['name' => 'Service frequency', 'name_sw' => 'Mzunguko wa huduma'],
                        ['name' => 'Contingency handling', 'name_sw' => 'Kushughulikia hali za dharura'],
                    ],
                ],
                [
                    'key' => 'safety_standards',
                    'name' => 'Safety Standards',
                    'name_sw' => 'Viwango vya Usalama',
                    'description' => 'Feedback about transportation safety',
                    'description_sw' => 'Maoni kuhusu usalama wa usafiri',
                    'subcategories' => [
                        ['name' => 'Vehicle maintenance', 'name_sw' => 'Matengenezo ya magari'],
                        ['name' => 'Driver behavior', 'name_sw' => 'Tabia ya dereva'],
                        ['name' => 'Safety equipment', 'name_sw' => 'Vifaa vya usalama'],
                        ['name' => 'Compliance adherence', 'name_sw' => 'Kufuata sheria'],
                        ['name' => 'Incident prevention', 'name_sw' => 'Kuzuia matukio'],
                    ],
                ],
                [
                    'key' => 'customer_experience',
                    'name' => 'Customer Experience',
                    'name_sw' => 'Uzoefu wa Mteja',
                    'description' => 'Feedback about customer service',
                    'description_sw' => 'Maoni kuhusu huduma kwa wateja',
                    'subcategories' => [
                        ['name' => 'Booking process', 'name_sw' => 'Mchakato wa kuhifadhi'],
                        ['name' => 'Staff courtesy', 'name_sw' => 'Adabu ya wafanyakazi'],
                        ['name' => 'Complaint handling', 'name_sw' => 'Kushughulikia malalamiko'],
                        ['name' => 'Information availability', 'name_sw' => 'Upatikanaji wa taarifa'],
                        ['name' => 'Price transparency', 'name_sw' => 'Uwazi wa bei'],
                    ],
                ],
                [
                    'key' => 'fleet_condition',
                    'name' => 'Fleet Condition',
                    'name_sw' => 'Hali ya Magari',
                    'description' => 'Feedback about vehicle/fleet condition',
                    'description_sw' => 'Maoni kuhusu hali ya gari/magari',
                    'subcategories' => [
                        ['name' => 'Vehicle cleanliness', 'name_sw' => 'Usafi wa gari'],
                        ['name' => 'Comfort level', 'name_sw' => 'Kiwango cha starehe'],
                        ['name' => 'Air conditioning', 'name_sw' => 'Ubaridi wa hewa'],
                        ['name' => 'Cargo handling equipment', 'name_sw' => 'Vifaa vya kushughulikia mizigo'],
                        ['name' => 'Accessibility features', 'name_sw' => 'Vipengele vya upatikanaji'],
                    ],
                ],
            ],
        ];
    }
}
