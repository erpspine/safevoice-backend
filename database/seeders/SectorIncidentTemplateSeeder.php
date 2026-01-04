<?php

namespace Database\Seeders;

use App\Models\SectorIncidentTemplate;
use Illuminate\Database\Seeder;

class SectorIncidentTemplateSeeder extends Seeder
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
                        'description' => $category['description'] ?? null,
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
                    'key' => 'academic_misconduct',
                    'name' => 'Academic Misconduct',
                    'subcategories' => [
                        'Plagiarism',
                        'Exam cheating',
                        'Grade manipulation',
                        'Falsification of academic records',
                    ],
                ],
                [
                    'key' => 'student_welfare',
                    'name' => 'Student Welfare',
                    'subcategories' => [
                        'Bullying',
                        'Cyberbullying',
                        'Harassment',
                        'Mental health concerns',
                        'Self-harm or suicidal ideation',
                    ],
                ],
                [
                    'key' => 'staff_misconduct',
                    'name' => 'Staff Misconduct',
                    'subcategories' => [
                        'Inappropriate relationships with students',
                        'Abuse of authority',
                        'Neglect of duty',
                        'Breach of professional ethics',
                    ],
                ],
                [
                    'key' => 'safeguarding',
                    'name' => 'Safeguarding',
                    'subcategories' => [
                        'Child protection concerns',
                        'Physical abuse',
                        'Emotional abuse',
                        'Sexual abuse',
                        'Neglect',
                    ],
                ],
                [
                    'key' => 'financial_mismanagement',
                    'name' => 'Financial Mismanagement',
                    'subcategories' => [
                        'Misuse of school funds',
                        'Fraudulent procurement',
                        'Bribery in admissions',
                        'Unauthorized fee collection',
                    ],
                ],
                [
                    'key' => 'safety_security',
                    'name' => 'Safety & Security',
                    'subcategories' => [
                        'Unsafe premises',
                        'Lack of emergency preparedness',
                        'Substance abuse on campus',
                        'Unauthorized visitors',
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'subcategories' => [
                        'Racial discrimination',
                        'Gender discrimination',
                        'Disability discrimination',
                        'Religious discrimination',
                    ],
                ],
            ],

            // CORPORATE WORKPLACE SECTOR
            'corporate_workplace' => [
                [
                    'key' => 'workplace_harassment',
                    'name' => 'Workplace Harassment',
                    'subcategories' => [
                        'Sexual harassment',
                        'Verbal abuse',
                        'Bullying',
                        'Intimidation',
                        'Hostile work environment',
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'subcategories' => [
                        'Gender discrimination',
                        'Racial discrimination',
                        'Age discrimination',
                        'Disability discrimination',
                        'Religious discrimination',
                    ],
                ],
                [
                    'key' => 'financial_fraud',
                    'name' => 'Financial Fraud',
                    'subcategories' => [
                        'Embezzlement',
                        'Expense fraud',
                        'Invoice manipulation',
                        'Payroll fraud',
                        'Asset misappropriation',
                    ],
                ],
                [
                    'key' => 'conflict_of_interest',
                    'name' => 'Conflict of Interest',
                    'subcategories' => [
                        'Undisclosed relationships',
                        'Outside business interests',
                        'Favoritism in hiring',
                        'Nepotism',
                    ],
                ],
                [
                    'key' => 'data_privacy_breach',
                    'name' => 'Data Privacy Breach',
                    'subcategories' => [
                        'Unauthorized data access',
                        'Data theft',
                        'GDPR violations',
                        'Customer data misuse',
                    ],
                ],
                [
                    'key' => 'health_safety',
                    'name' => 'Health & Safety',
                    'subcategories' => [
                        'Unsafe working conditions',
                        'Failure to report accidents',
                        'Violation of safety protocols',
                        'Inadequate PPE',
                    ],
                ],
                [
                    'key' => 'policy_violations',
                    'name' => 'Policy Violations',
                    'subcategories' => [
                        'Code of conduct breaches',
                        'IT policy violations',
                        'Attendance fraud',
                        'Substance abuse',
                    ],
                ],
                [
                    'key' => 'retaliation',
                    'name' => 'Retaliation',
                    'subcategories' => [
                        'Whistleblower retaliation',
                        'Demotion after complaint',
                        'Unfair termination',
                        'Exclusion from opportunities',
                    ],
                ],
            ],

            // FINANCIAL & INSURANCE SECTOR
            'financial_insurance' => [
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'subcategories' => [
                        'Insurance fraud',
                        'Claims manipulation',
                        'Identity theft',
                        'Money laundering',
                        'Insider trading',
                    ],
                ],
                [
                    'key' => 'regulatory_violations',
                    'name' => 'Regulatory Violations',
                    'subcategories' => [
                        'AML violations',
                        'KYC non-compliance',
                        'Licensing breaches',
                        'Reporting failures',
                    ],
                ],
                [
                    'key' => 'customer_harm',
                    'name' => 'Customer Harm',
                    'subcategories' => [
                        'Mis-selling of products',
                        'Unauthorized transactions',
                        'Unfair lending practices',
                        'Privacy violations',
                    ],
                ],
                [
                    'key' => 'conflict_of_interest',
                    'name' => 'Conflict of Interest',
                    'subcategories' => [
                        'Self-dealing',
                        'Related party transactions',
                        'Undisclosed commissions',
                    ],
                ],
                [
                    'key' => 'workplace_misconduct',
                    'name' => 'Workplace Misconduct',
                    'subcategories' => [
                        'Harassment',
                        'Discrimination',
                        'Bullying',
                        'Retaliation',
                    ],
                ],
                [
                    'key' => 'cybersecurity',
                    'name' => 'Cybersecurity',
                    'subcategories' => [
                        'Data breaches',
                        'Phishing incidents',
                        'System vulnerabilities',
                        'Unauthorized access',
                    ],
                ],
            ],

            // HEALTHCARE SECTOR
            'healthcare' => [
                [
                    'key' => 'patient_safety',
                    'name' => 'Patient Safety',
                    'subcategories' => [
                        'Medical errors',
                        'Medication errors',
                        'Misdiagnosis',
                        'Surgical errors',
                        'Inadequate care',
                    ],
                ],
                [
                    'key' => 'fraud_billing',
                    'name' => 'Fraud & Billing',
                    'subcategories' => [
                        'Insurance fraud',
                        'Overbilling',
                        'Phantom billing',
                        'Kickbacks',
                    ],
                ],
                [
                    'key' => 'professional_misconduct',
                    'name' => 'Professional Misconduct',
                    'subcategories' => [
                        'Practicing without license',
                        'Falsification of credentials',
                        'Sexual misconduct',
                        'Boundary violations',
                    ],
                ],
                [
                    'key' => 'patient_rights',
                    'name' => 'Patient Rights',
                    'subcategories' => [
                        'Privacy violations (HIPAA)',
                        'Informed consent violations',
                        'Discrimination in care',
                        'Abuse or neglect',
                    ],
                ],
                [
                    'key' => 'workplace_safety',
                    'name' => 'Workplace Safety',
                    'subcategories' => [
                        'Exposure to hazardous materials',
                        'Inadequate infection control',
                        'Violence in workplace',
                        'Equipment failures',
                    ],
                ],
                [
                    'key' => 'regulatory_compliance',
                    'name' => 'Regulatory Compliance',
                    'subcategories' => [
                        'License violations',
                        'Accreditation issues',
                        'Research ethics violations',
                        'Drug handling violations',
                    ],
                ],
            ],

            // MANUFACTURING & INDUSTRIAL SECTOR
            'manufacturing_industrial' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'subcategories' => [
                        'Equipment safety failures',
                        'Lack of PPE',
                        'Chemical exposure',
                        'Fire hazards',
                        'Electrical hazards',
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'subcategories' => [
                        'Illegal waste disposal',
                        'Air pollution',
                        'Water contamination',
                        'Hazardous material handling',
                    ],
                ],
                [
                    'key' => 'quality_control',
                    'name' => 'Quality Control',
                    'subcategories' => [
                        'Product defects',
                        'Falsified testing results',
                        'Non-compliance with standards',
                        'Counterfeit materials',
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'subcategories' => [
                        'Child labor',
                        'Forced overtime',
                        'Wage theft',
                        'Unsafe working conditions',
                    ],
                ],
                [
                    'key' => 'theft_fraud',
                    'name' => 'Theft & Fraud',
                    'subcategories' => [
                        'Inventory theft',
                        'Equipment theft',
                        'Procurement fraud',
                        'Intellectual property theft',
                    ],
                ],
                [
                    'key' => 'workplace_harassment',
                    'name' => 'Workplace Harassment',
                    'subcategories' => [
                        'Sexual harassment',
                        'Bullying',
                        'Discrimination',
                        'Retaliation',
                    ],
                ],
            ],

            // CONSTRUCTION & ENGINEERING SECTOR
            'construction_engineering' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'subcategories' => [
                        'Fall hazards',
                        'Scaffolding failures',
                        'Excavation dangers',
                        'Electrical hazards',
                        'Heavy equipment accidents',
                    ],
                ],
                [
                    'key' => 'code_violations',
                    'name' => 'Code Violations',
                    'subcategories' => [
                        'Building code violations',
                        'Permit violations',
                        'Substandard materials',
                        'Structural deficiencies',
                    ],
                ],
                [
                    'key' => 'contract_fraud',
                    'name' => 'Contract Fraud',
                    'subcategories' => [
                        'Bid rigging',
                        'Overbilling',
                        'False certifications',
                        'Kickbacks',
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'subcategories' => [
                        'Illegal dumping',
                        'Asbestos handling violations',
                        'Erosion control failures',
                        'Wetland destruction',
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'subcategories' => [
                        'Undocumented workers',
                        'Wage theft',
                        'Unsafe conditions',
                        'Workers compensation fraud',
                    ],
                ],
                [
                    'key' => 'professional_misconduct',
                    'name' => 'Professional Misconduct',
                    'subcategories' => [
                        'Engineering malpractice',
                        'Falsified inspections',
                        'Unlicensed practice',
                        'Conflict of interest',
                    ],
                ],
            ],

            // SECURITY & UNIFORMED SERVICES SECTOR
            'security_uniformed_services' => [
                [
                    'key' => 'abuse_of_authority',
                    'name' => 'Abuse of Authority',
                    'subcategories' => [
                        'Excessive force',
                        'Unlawful detention',
                        'Harassment of civilians',
                        'Abuse of power',
                    ],
                ],
                [
                    'key' => 'corruption',
                    'name' => 'Corruption',
                    'subcategories' => [
                        'Bribery',
                        'Extortion',
                        'Theft of seized property',
                        'Falsifying reports',
                    ],
                ],
                [
                    'key' => 'misconduct',
                    'name' => 'Misconduct',
                    'subcategories' => [
                        'Dereliction of duty',
                        'Insubordination',
                        'Unauthorized disclosure',
                        'Off-duty misconduct',
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'subcategories' => [
                        'Racial profiling',
                        'Gender discrimination',
                        'Religious discrimination',
                        'LGBTQ+ discrimination',
                    ],
                ],
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'subcategories' => [
                        'Improper weapon handling',
                        'Vehicle safety violations',
                        'Training deficiencies',
                        'Equipment failures',
                    ],
                ],
                [
                    'key' => 'workplace_issues',
                    'name' => 'Workplace Issues',
                    'subcategories' => [
                        'Sexual harassment',
                        'Bullying',
                        'Hazing',
                        'Retaliation',
                    ],
                ],
            ],

            // HOSPITALITY, TRAVEL & TOURISM SECTOR
            'hospitality_travel_tourism' => [
                [
                    'key' => 'guest_safety',
                    'name' => 'Guest Safety',
                    'subcategories' => [
                        'Food safety violations',
                        'Hygiene issues',
                        'Security breaches',
                        'Unsafe premises',
                    ],
                ],
                [
                    'key' => 'employee_misconduct',
                    'name' => 'Employee Misconduct',
                    'subcategories' => [
                        'Theft from guests',
                        'Privacy violations',
                        'Harassment of guests',
                        'Unauthorized access',
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'subcategories' => [
                        'Wage theft',
                        'Tip theft',
                        'Forced overtime',
                        'Discrimination',
                    ],
                ],
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'subcategories' => [
                        'Booking fraud',
                        'Credit card fraud',
                        'Overbilling',
                        'False advertising',
                    ],
                ],
                [
                    'key' => 'health_safety',
                    'name' => 'Health & Safety',
                    'subcategories' => [
                        'Pool safety issues',
                        'Fire code violations',
                        'Emergency preparedness',
                        'Pest infestations',
                    ],
                ],
                [
                    'key' => 'workplace_harassment',
                    'name' => 'Workplace Harassment',
                    'subcategories' => [
                        'Sexual harassment',
                        'Bullying',
                        'Hostile work environment',
                        'Retaliation',
                    ],
                ],
            ],

            // NGO, CSO & DONOR-FUNDED SECTOR
            'ngo_cso_donor_funded' => [
                [
                    'key' => 'financial_misconduct',
                    'name' => 'Financial Misconduct',
                    'subcategories' => [
                        'Misuse of donor funds',
                        'Embezzlement',
                        'Fraudulent reporting',
                        'Kickbacks in procurement',
                    ],
                ],
                [
                    'key' => 'program_fraud',
                    'name' => 'Program Fraud',
                    'subcategories' => [
                        'Falsified beneficiary numbers',
                        'Ghost projects',
                        'Diversion of aid',
                        'False impact reporting',
                    ],
                ],
                [
                    'key' => 'safeguarding',
                    'name' => 'Safeguarding',
                    'subcategories' => [
                        'Sexual exploitation',
                        'Abuse of beneficiaries',
                        'Child protection failures',
                        'Trafficking concerns',
                    ],
                ],
                [
                    'key' => 'governance_violations',
                    'name' => 'Governance Violations',
                    'subcategories' => [
                        'Conflict of interest',
                        'Board misconduct',
                        'Policy violations',
                        'Lack of accountability',
                    ],
                ],
                [
                    'key' => 'workplace_misconduct',
                    'name' => 'Workplace Misconduct',
                    'subcategories' => [
                        'Harassment',
                        'Discrimination',
                        'Bullying',
                        'Retaliation',
                    ],
                ],
                [
                    'key' => 'compliance_violations',
                    'name' => 'Compliance Violations',
                    'subcategories' => [
                        'Donor requirement breaches',
                        'Tax compliance issues',
                        'Registration violations',
                        'Reporting failures',
                    ],
                ],
            ],

            // RELIGIOUS INSTITUTIONS SECTOR
            'religious_institutions' => [
                [
                    'key' => 'financial_misconduct',
                    'name' => 'Financial Misconduct',
                    'subcategories' => [
                        'Misuse of donations',
                        'Embezzlement',
                        'Fraudulent fundraising',
                        'Lack of financial transparency',
                    ],
                ],
                [
                    'key' => 'safeguarding',
                    'name' => 'Safeguarding',
                    'subcategories' => [
                        'Child abuse',
                        'Sexual misconduct',
                        'Vulnerable adult abuse',
                        'Domestic violence',
                    ],
                ],
                [
                    'key' => 'leadership_misconduct',
                    'name' => 'Leadership Misconduct',
                    'subcategories' => [
                        'Abuse of spiritual authority',
                        'Manipulation',
                        'Coercion',
                        'Cult-like practices',
                    ],
                ],
                [
                    'key' => 'discrimination',
                    'name' => 'Discrimination',
                    'subcategories' => [
                        'Gender discrimination',
                        'Racial discrimination',
                        'LGBTQ+ discrimination',
                        'Caste discrimination',
                    ],
                ],
                [
                    'key' => 'governance_violations',
                    'name' => 'Governance Violations',
                    'subcategories' => [
                        'Lack of accountability',
                        'Nepotism',
                        'Violation of bylaws',
                        'Cover-ups',
                    ],
                ],
                [
                    'key' => 'workplace_issues',
                    'name' => 'Workplace Issues',
                    'subcategories' => [
                        'Harassment',
                        'Unfair treatment',
                        'Wage issues',
                        'Hostile environment',
                    ],
                ],
            ],

            // TRANSPORT & LOGISTICS SECTOR
            'transport_logistics' => [
                [
                    'key' => 'safety_violations',
                    'name' => 'Safety Violations',
                    'subcategories' => [
                        'Driver fatigue violations',
                        'Vehicle maintenance failures',
                        'Overloading',
                        'Speed violations',
                        'Hazmat handling issues',
                    ],
                ],
                [
                    'key' => 'fraud',
                    'name' => 'Fraud',
                    'subcategories' => [
                        'Cargo theft',
                        'Fuel theft',
                        'Invoice fraud',
                        'Insurance fraud',
                    ],
                ],
                [
                    'key' => 'regulatory_violations',
                    'name' => 'Regulatory Violations',
                    'subcategories' => [
                        'Licensing violations',
                        'Hours of service violations',
                        'Documentation fraud',
                        'Import/export violations',
                    ],
                ],
                [
                    'key' => 'labor_violations',
                    'name' => 'Labor Violations',
                    'subcategories' => [
                        'Wage theft',
                        'Unpaid overtime',
                        'Discrimination',
                        'Unsafe working conditions',
                    ],
                ],
                [
                    'key' => 'environmental_violations',
                    'name' => 'Environmental Violations',
                    'subcategories' => [
                        'Emissions violations',
                        'Illegal dumping',
                        'Fuel spills',
                        'Noise violations',
                    ],
                ],
                [
                    'key' => 'workplace_misconduct',
                    'name' => 'Workplace Misconduct',
                    'subcategories' => [
                        'Harassment',
                        'Bullying',
                        'Substance abuse',
                        'Retaliation',
                    ],
                ],
            ],
        ];
    }
}
