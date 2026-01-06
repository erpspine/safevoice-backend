<?php

namespace Database\Seeders;

use App\Models\SectorFeedbackTemplate;
use Illuminate\Database\Seeder;

class SectorFeedbackTemplateSeeder extends Seeder
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
                        'description' => $category['description'] ?? null,
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
                            'subcategory_name' => $subcategory,
                        ],
                        [
                            'category_name' => $category['name'],
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
     * Get all sector templates configuration.
     */
    private function getSectorTemplates(): array
    {
        return [
            // EDUCATION SECTOR
            'education' => [
                [
                    'key' => 'teaching_quality',
                    'name' => 'Teaching Quality',
                    'description' => 'Feedback related to teaching and instruction quality',
                    'subcategories' => [
                        'Course content relevance',
                        'Teaching methods effectiveness',
                        'Instructor engagement',
                        'Assessment fairness',
                        'Learning materials quality',
                    ],
                ],
                [
                    'key' => 'facilities_resources',
                    'name' => 'Facilities & Resources',
                    'description' => 'Feedback about campus facilities and learning resources',
                    'subcategories' => [
                        'Classroom conditions',
                        'Library services',
                        'Laboratory equipment',
                        'IT infrastructure',
                        'Sports facilities',
                    ],
                ],
                [
                    'key' => 'student_services',
                    'name' => 'Student Services',
                    'description' => 'Feedback on student support services',
                    'subcategories' => [
                        'Counseling services',
                        'Career guidance',
                        'Admissions process',
                        'Financial aid support',
                        'Health services',
                    ],
                ],
                [
                    'key' => 'campus_environment',
                    'name' => 'Campus Environment',
                    'description' => 'Feedback about the overall campus atmosphere',
                    'subcategories' => [
                        'Safety and security',
                        'Cleanliness',
                        'Cafeteria services',
                        'Transportation',
                        'Student activities',
                    ],
                ],
            ],

            // CORPORATE/WORKPLACE SECTOR
            'corporate_workplace' => [
                [
                    'key' => 'work_environment',
                    'name' => 'Work Environment',
                    'description' => 'Feedback about workplace conditions',
                    'subcategories' => [
                        'Office facilities',
                        'Work-life balance',
                        'Team collaboration',
                        'Remote work support',
                        'Workplace safety',
                    ],
                ],
                [
                    'key' => 'management_leadership',
                    'name' => 'Management & Leadership',
                    'description' => 'Feedback about management practices',
                    'subcategories' => [
                        'Communication from leadership',
                        'Decision-making transparency',
                        'Performance management',
                        'Recognition programs',
                        'Career development opportunities',
                    ],
                ],
                [
                    'key' => 'compensation_benefits',
                    'name' => 'Compensation & Benefits',
                    'description' => 'Feedback about pay and employee benefits',
                    'subcategories' => [
                        'Salary competitiveness',
                        'Bonus structure',
                        'Health insurance',
                        'Retirement benefits',
                        'Leave policies',
                    ],
                ],
                [
                    'key' => 'professional_development',
                    'name' => 'Professional Development',
                    'description' => 'Feedback about learning and growth opportunities',
                    'subcategories' => [
                        'Training programs',
                        'Skill development',
                        'Mentorship programs',
                        'Conference attendance',
                        'Certification support',
                    ],
                ],
            ],

            // FINANCIAL & INSURANCE SECTOR
            'financial_insurance' => [
                [
                    'key' => 'customer_service',
                    'name' => 'Customer Service',
                    'description' => 'Feedback about customer-facing services',
                    'subcategories' => [
                        'Response time',
                        'Staff knowledge',
                        'Problem resolution',
                        'Communication clarity',
                        'Service accessibility',
                    ],
                ],
                [
                    'key' => 'products_services',
                    'name' => 'Products & Services',
                    'description' => 'Feedback about financial products and services',
                    'subcategories' => [
                        'Product range',
                        'Pricing transparency',
                        'Terms and conditions clarity',
                        'Digital services',
                        'Product innovation',
                    ],
                ],
                [
                    'key' => 'branch_experience',
                    'name' => 'Branch Experience',
                    'description' => 'Feedback about physical branch services',
                    'subcategories' => [
                        'Wait times',
                        'Branch facilities',
                        'ATM availability',
                        'Branch locations',
                        'Operating hours',
                    ],
                ],
                [
                    'key' => 'digital_banking',
                    'name' => 'Digital Banking',
                    'description' => 'Feedback about online and mobile services',
                    'subcategories' => [
                        'Mobile app usability',
                        'Online platform features',
                        'Transaction security',
                        'System reliability',
                        'Digital onboarding',
                    ],
                ],
            ],

            // HEALTHCARE SECTOR
            'healthcare' => [
                [
                    'key' => 'patient_care',
                    'name' => 'Patient Care',
                    'description' => 'Feedback about quality of care received',
                    'subcategories' => [
                        'Medical staff competence',
                        'Nursing care quality',
                        'Treatment effectiveness',
                        'Pain management',
                        'Care coordination',
                    ],
                ],
                [
                    'key' => 'facilities_equipment',
                    'name' => 'Facilities & Equipment',
                    'description' => 'Feedback about healthcare facilities',
                    'subcategories' => [
                        'Facility cleanliness',
                        'Room comfort',
                        'Medical equipment',
                        'Diagnostic facilities',
                        'Parking availability',
                    ],
                ],
                [
                    'key' => 'administrative_services',
                    'name' => 'Administrative Services',
                    'description' => 'Feedback about administrative processes',
                    'subcategories' => [
                        'Appointment scheduling',
                        'Billing transparency',
                        'Insurance processing',
                        'Medical records access',
                        'Wait times',
                    ],
                ],
                [
                    'key' => 'communication',
                    'name' => 'Communication',
                    'description' => 'Feedback about healthcare communication',
                    'subcategories' => [
                        'Doctor-patient communication',
                        'Test result delivery',
                        'Discharge instructions',
                        'Follow-up communication',
                        'Health education',
                    ],
                ],
            ],

            // MANUFACTURING & INDUSTRIAL SECTOR
            'manufacturing_industrial' => [
                [
                    'key' => 'workplace_safety',
                    'name' => 'Workplace Safety',
                    'description' => 'Feedback about safety practices and conditions',
                    'subcategories' => [
                        'Safety equipment availability',
                        'Safety training adequacy',
                        'Hazard identification',
                        'Emergency procedures',
                        'Incident response',
                    ],
                ],
                [
                    'key' => 'production_processes',
                    'name' => 'Production Processes',
                    'description' => 'Feedback about manufacturing operations',
                    'subcategories' => [
                        'Process efficiency',
                        'Quality control',
                        'Equipment maintenance',
                        'Production scheduling',
                        'Resource allocation',
                    ],
                ],
                [
                    'key' => 'working_conditions',
                    'name' => 'Working Conditions',
                    'description' => 'Feedback about factory floor conditions',
                    'subcategories' => [
                        'Temperature control',
                        'Noise levels',
                        'Break facilities',
                        'Shift scheduling',
                        'Physical workload',
                    ],
                ],
                [
                    'key' => 'employee_relations',
                    'name' => 'Employee Relations',
                    'description' => 'Feedback about management-worker relations',
                    'subcategories' => [
                        'Supervisor communication',
                        'Union relations',
                        'Grievance handling',
                        'Team dynamics',
                        'Recognition programs',
                    ],
                ],
            ],

            // CONSTRUCTION & ENGINEERING SECTOR
            'construction_engineering' => [
                [
                    'key' => 'site_safety',
                    'name' => 'Site Safety',
                    'description' => 'Feedback about construction site safety',
                    'subcategories' => [
                        'PPE provision',
                        'Safety briefings',
                        'Site access control',
                        'Scaffolding safety',
                        'Heavy equipment handling',
                    ],
                ],
                [
                    'key' => 'project_management',
                    'name' => 'Project Management',
                    'description' => 'Feedback about project execution',
                    'subcategories' => [
                        'Timeline management',
                        'Resource coordination',
                        'Communication with stakeholders',
                        'Change order handling',
                        'Budget management',
                    ],
                ],
                [
                    'key' => 'quality_standards',
                    'name' => 'Quality Standards',
                    'description' => 'Feedback about construction quality',
                    'subcategories' => [
                        'Material quality',
                        'Workmanship standards',
                        'Inspection processes',
                        'Compliance adherence',
                        'Defect rectification',
                    ],
                ],
                [
                    'key' => 'workforce_conditions',
                    'name' => 'Workforce Conditions',
                    'description' => 'Feedback about worker welfare',
                    'subcategories' => [
                        'Wage payment timeliness',
                        'Accommodation quality',
                        'Rest periods',
                        'Training opportunities',
                        'Contract terms clarity',
                    ],
                ],
            ],

            // SECURITY & UNIFORMED SERVICES SECTOR
            'security_uniformed_services' => [
                [
                    'key' => 'service_delivery',
                    'name' => 'Service Delivery',
                    'description' => 'Feedback about security service quality',
                    'subcategories' => [
                        'Response time',
                        'Patrol effectiveness',
                        'Incident handling',
                        'Client communication',
                        'Report accuracy',
                    ],
                ],
                [
                    'key' => 'personnel_conduct',
                    'name' => 'Personnel Conduct',
                    'description' => 'Feedback about security staff behavior',
                    'subcategories' => [
                        'Professionalism',
                        'Appearance standards',
                        'Customer interaction',
                        'Protocol adherence',
                        'Conflict resolution',
                    ],
                ],
                [
                    'key' => 'equipment_resources',
                    'name' => 'Equipment & Resources',
                    'description' => 'Feedback about security equipment',
                    'subcategories' => [
                        'Uniform quality',
                        'Communication devices',
                        'Security technology',
                        'Vehicle maintenance',
                        'Control room facilities',
                    ],
                ],
                [
                    'key' => 'training_development',
                    'name' => 'Training & Development',
                    'description' => 'Feedback about security training',
                    'subcategories' => [
                        'Initial training quality',
                        'Ongoing training',
                        'Certification programs',
                        'Skills assessment',
                        'Career progression',
                    ],
                ],
            ],

            // HOSPITALITY, TRAVEL & TOURISM SECTOR
            'hospitality_travel_tourism' => [
                [
                    'key' => 'guest_experience',
                    'name' => 'Guest Experience',
                    'description' => 'Feedback about customer experience',
                    'subcategories' => [
                        'Check-in/check-out process',
                        'Room quality',
                        'Staff friendliness',
                        'Service responsiveness',
                        'Special requests handling',
                    ],
                ],
                [
                    'key' => 'food_beverage',
                    'name' => 'Food & Beverage',
                    'description' => 'Feedback about dining services',
                    'subcategories' => [
                        'Food quality',
                        'Menu variety',
                        'Service speed',
                        'Dietary accommodations',
                        'Presentation standards',
                    ],
                ],
                [
                    'key' => 'facilities_amenities',
                    'name' => 'Facilities & Amenities',
                    'description' => 'Feedback about hotel/resort facilities',
                    'subcategories' => [
                        'Pool and spa',
                        'Fitness center',
                        'Business center',
                        'Wifi connectivity',
                        'Entertainment facilities',
                    ],
                ],
                [
                    'key' => 'cleanliness_hygiene',
                    'name' => 'Cleanliness & Hygiene',
                    'description' => 'Feedback about cleanliness standards',
                    'subcategories' => [
                        'Room cleanliness',
                        'Public area maintenance',
                        'Bathroom hygiene',
                        'Linen quality',
                        'COVID-19 protocols',
                    ],
                ],
            ],

            // NGO/CSO/DONOR FUNDED SECTOR
            'ngo_cso_donor_funded' => [
                [
                    'key' => 'program_effectiveness',
                    'name' => 'Program Effectiveness',
                    'description' => 'Feedback about program impact',
                    'subcategories' => [
                        'Beneficiary reach',
                        'Program relevance',
                        'Outcome achievement',
                        'Community engagement',
                        'Sustainability measures',
                    ],
                ],
                [
                    'key' => 'donor_relations',
                    'name' => 'Donor Relations',
                    'description' => 'Feedback about donor engagement',
                    'subcategories' => [
                        'Reporting quality',
                        'Communication frequency',
                        'Fund utilization transparency',
                        'Impact documentation',
                        'Partnership management',
                    ],
                ],
                [
                    'key' => 'organizational_culture',
                    'name' => 'Organizational Culture',
                    'description' => 'Feedback about workplace culture',
                    'subcategories' => [
                        'Values alignment',
                        'Staff wellbeing',
                        'Volunteer management',
                        'Diversity and inclusion',
                        'Work-life balance',
                    ],
                ],
                [
                    'key' => 'governance',
                    'name' => 'Governance',
                    'description' => 'Feedback about organizational governance',
                    'subcategories' => [
                        'Decision-making processes',
                        'Policy adherence',
                        'Accountability measures',
                        'Board effectiveness',
                        'Conflict of interest management',
                    ],
                ],
            ],

            // RELIGIOUS INSTITUTIONS SECTOR
            'religious_institutions' => [
                [
                    'key' => 'spiritual_programs',
                    'name' => 'Spiritual Programs',
                    'description' => 'Feedback about religious services and programs',
                    'subcategories' => [
                        'Service quality',
                        'Sermon relevance',
                        'Music and worship',
                        'Youth programs',
                        'Adult education',
                    ],
                ],
                [
                    'key' => 'community_engagement',
                    'name' => 'Community Engagement',
                    'description' => 'Feedback about community involvement',
                    'subcategories' => [
                        'Outreach programs',
                        'Fellowship activities',
                        'Community support',
                        'Social events',
                        'Volunteer opportunities',
                    ],
                ],
                [
                    'key' => 'facilities_services',
                    'name' => 'Facilities & Services',
                    'description' => 'Feedback about institution facilities',
                    'subcategories' => [
                        'Worship space',
                        'Meeting rooms',
                        'Parking facilities',
                        'Accessibility',
                        'Childcare services',
                    ],
                ],
                [
                    'key' => 'pastoral_care',
                    'name' => 'Pastoral Care',
                    'description' => 'Feedback about spiritual guidance',
                    'subcategories' => [
                        'Counseling availability',
                        'Hospital visits',
                        'Marriage guidance',
                        'Grief support',
                        'Crisis intervention',
                    ],
                ],
            ],

            // TRANSPORT & LOGISTICS SECTOR
            'transport_logistics' => [
                [
                    'key' => 'service_reliability',
                    'name' => 'Service Reliability',
                    'description' => 'Feedback about transport service reliability',
                    'subcategories' => [
                        'On-time performance',
                        'Route coverage',
                        'Schedule adherence',
                        'Service frequency',
                        'Contingency handling',
                    ],
                ],
                [
                    'key' => 'safety_standards',
                    'name' => 'Safety Standards',
                    'description' => 'Feedback about transportation safety',
                    'subcategories' => [
                        'Vehicle maintenance',
                        'Driver behavior',
                        'Safety equipment',
                        'Compliance adherence',
                        'Incident prevention',
                    ],
                ],
                [
                    'key' => 'customer_experience',
                    'name' => 'Customer Experience',
                    'description' => 'Feedback about customer service',
                    'subcategories' => [
                        'Booking process',
                        'Staff courtesy',
                        'Complaint handling',
                        'Information availability',
                        'Price transparency',
                    ],
                ],
                [
                    'key' => 'fleet_condition',
                    'name' => 'Fleet Condition',
                    'description' => 'Feedback about vehicle/fleet condition',
                    'subcategories' => [
                        'Vehicle cleanliness',
                        'Comfort level',
                        'Air conditioning',
                        'Cargo handling equipment',
                        'Accessibility features',
                    ],
                ],
            ],
        ];
    }
}
