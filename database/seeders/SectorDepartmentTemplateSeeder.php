<?php

namespace Database\Seeders;

use App\Models\SectorDepartmentTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectorDepartmentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectors = [
            // Education Sector
            [
                'sector' => 'education',
                'departments' => [
                    ['code' => 'EDU-ADM', 'name' => 'Administration', 'description' => 'General administrative functions and school management'],
                    ['code' => 'EDU-ACD', 'name' => 'Academic Affairs', 'description' => 'Curriculum, teaching, and academic programs management'],
                    ['code' => 'EDU-DSC', 'name' => 'Discipline & Student Affairs', 'description' => 'Student behavior, discipline, and student life management'],
                    ['code' => 'EDU-CNS', 'name' => 'Counseling & Welfare', 'description' => 'Student counseling, mental health, and welfare services'],
                    ['code' => 'EDU-EXM', 'name' => 'Examinations', 'description' => 'Examination administration, grading, and certification'],
                    ['code' => 'EDU-FIN', 'name' => 'Finance', 'description' => 'Financial management, fees, and budgeting'],
                    ['code' => 'EDU-HR', 'name' => 'Human Resources', 'description' => 'Staff recruitment, management, and development'],
                    ['code' => 'EDU-ICT', 'name' => 'ICT / E-Learning', 'description' => 'Information technology and e-learning systems'],
                    ['code' => 'EDU-FAC', 'name' => 'Facilities & Maintenance', 'description' => 'Building maintenance and facilities management'],
                    ['code' => 'EDU-TRN', 'name' => 'Transport', 'description' => 'Student and staff transportation services'],
                    ['code' => 'EDU-SEC', 'name' => 'Security', 'description' => 'Campus security and safety'],
                ],
            ],

            // Corporate & Workplace Sector
            [
                'sector' => 'corporate_workplace',
                'departments' => [
                    ['code' => 'COR-HR', 'name' => 'Human Resources', 'description' => 'Employee management, recruitment, and development'],
                    ['code' => 'COR-FIN', 'name' => 'Finance', 'description' => 'Financial planning, accounting, and reporting'],
                    ['code' => 'COR-OPS', 'name' => 'Operations', 'description' => 'Day-to-day business operations management'],
                    ['code' => 'COR-SLS', 'name' => 'Sales', 'description' => 'Sales activities and revenue generation'],
                    ['code' => 'COR-MKT', 'name' => 'Marketing', 'description' => 'Marketing, branding, and communications'],
                    ['code' => 'COR-CS', 'name' => 'Customer Service', 'description' => 'Customer support and relationship management'],
                    ['code' => 'COR-PRC', 'name' => 'Procurement', 'description' => 'Purchasing and supply chain management'],
                    ['code' => 'COR-ICT', 'name' => 'ICT / IT', 'description' => 'Information technology and systems'],
                    ['code' => 'COR-LEG', 'name' => 'Legal & Compliance', 'description' => 'Legal affairs and regulatory compliance'],
                    ['code' => 'COR-QA', 'name' => 'Quality Assurance', 'description' => 'Quality control and assurance'],
                    ['code' => 'COR-HSE', 'name' => 'Health, Safety & Environment (HSE)', 'description' => 'Workplace health, safety, and environmental compliance'],
                    ['code' => 'COR-SEC', 'name' => 'Security', 'description' => 'Corporate security and asset protection'],
                ],
            ],

            // Financial & Insurance Sector
            [
                'sector' => 'financial_insurance',
                'departments' => [
                    ['code' => 'FIN-OPS', 'name' => 'Operations', 'description' => 'Core banking/insurance operations'],
                    ['code' => 'FIN-RET', 'name' => 'Retail Banking / Sales', 'description' => 'Customer-facing sales and services'],
                    ['code' => 'FIN-CRD', 'name' => 'Credit / Underwriting', 'description' => 'Credit assessment and loan underwriting'],
                    ['code' => 'FIN-CLM', 'name' => 'Claims', 'description' => 'Insurance claims processing'],
                    ['code' => 'FIN-RIS', 'name' => 'Risk Management', 'description' => 'Risk assessment and mitigation'],
                    ['code' => 'FIN-CMP', 'name' => 'Compliance', 'description' => 'Regulatory compliance and reporting'],
                    ['code' => 'FIN-AUD', 'name' => 'Internal Audit', 'description' => 'Internal auditing and controls'],
                    ['code' => 'FIN-FIN', 'name' => 'Finance & Accounts', 'description' => 'Financial management and accounting'],
                    ['code' => 'FIN-HR', 'name' => 'Human Resources', 'description' => 'HR management and staff development'],
                    ['code' => 'FIN-ICT', 'name' => 'ICT / Core Systems', 'description' => 'IT systems and digital banking'],
                    ['code' => 'FIN-SEC', 'name' => 'Security', 'description' => 'Physical and cyber security'],
                ],
            ],

            // Healthcare Sector
            [
                'sector' => 'healthcare',
                'departments' => [
                    ['code' => 'HLT-OPD', 'name' => 'Outpatient (OPD)', 'description' => 'Outpatient services and consultations'],
                    ['code' => 'HLT-IPD', 'name' => 'Inpatient (Wards)', 'description' => 'Inpatient care and ward management'],
                    ['code' => 'HLT-EMR', 'name' => 'Emergency', 'description' => 'Emergency and trauma services'],
                    ['code' => 'HLT-NRS', 'name' => 'Nursing', 'description' => 'Nursing services and patient care'],
                    ['code' => 'HLT-PHR', 'name' => 'Pharmacy', 'description' => 'Pharmaceutical services and drug dispensing'],
                    ['code' => 'HLT-LAB', 'name' => 'Laboratory', 'description' => 'Medical laboratory and diagnostics'],
                    ['code' => 'HLT-RAD', 'name' => 'Radiology / Imaging', 'description' => 'Medical imaging and radiology services'],
                    ['code' => 'HLT-ADM', 'name' => 'Hospital Administration', 'description' => 'Hospital management and administration'],
                    ['code' => 'HLT-HR', 'name' => 'Human Resources', 'description' => 'Staff management and HR services'],
                    ['code' => 'HLT-FIN', 'name' => 'Finance & Billing', 'description' => 'Financial management and patient billing'],
                    ['code' => 'HLT-REC', 'name' => 'Medical Records', 'description' => 'Patient records and health information'],
                    ['code' => 'HLT-IPC', 'name' => 'Infection Prevention & Control', 'description' => 'Infection control and prevention'],
                    ['code' => 'HLT-ICT', 'name' => 'ICT / HIS Support', 'description' => 'Health information systems and IT'],
                    ['code' => 'HLT-SEC', 'name' => 'Security', 'description' => 'Hospital security and safety'],
                ],
            ],

            // Manufacturing & Industrial Sector
            [
                'sector' => 'manufacturing_industrial',
                'departments' => [
                    ['code' => 'MFG-PRD', 'name' => 'Production', 'description' => 'Manufacturing and production operations'],
                    ['code' => 'MFG-QA', 'name' => 'Quality Assurance / QC', 'description' => 'Quality control and assurance'],
                    ['code' => 'MFG-ENG', 'name' => 'Engineering & Maintenance', 'description' => 'Equipment engineering and maintenance'],
                    ['code' => 'MFG-HSE', 'name' => 'Health, Safety & Environment (HSE)', 'description' => 'Workplace safety and environmental compliance'],
                    ['code' => 'MFG-STR', 'name' => 'Stores / Warehouse', 'description' => 'Inventory and warehouse management'],
                    ['code' => 'MFG-PRC', 'name' => 'Procurement', 'description' => 'Raw materials and supplies procurement'],
                    ['code' => 'MFG-LOG', 'name' => 'Logistics & Dispatch', 'description' => 'Product logistics and distribution'],
                    ['code' => 'MFG-FIN', 'name' => 'Finance', 'description' => 'Financial management and cost control'],
                    ['code' => 'MFG-HR', 'name' => 'Human Resources', 'description' => 'Workforce management and HR'],
                    ['code' => 'MFG-ICT', 'name' => 'ICT / Automation', 'description' => 'IT systems and industrial automation'],
                    ['code' => 'MFG-SEC', 'name' => 'Security', 'description' => 'Plant security and asset protection'],
                ],
            ],

            // Construction & Engineering Sector
            [
                'sector' => 'construction_engineering',
                'departments' => [
                    ['code' => 'CON-PMO', 'name' => 'Project Management', 'description' => 'Project planning and management'],
                    ['code' => 'CON-SIT', 'name' => 'Site Operations', 'description' => 'Construction site operations'],
                    ['code' => 'CON-QA', 'name' => 'Quality & Compliance', 'description' => 'Quality control and regulatory compliance'],
                    ['code' => 'CON-HSE', 'name' => 'Health & Safety (HSE)', 'description' => 'Construction safety and health'],
                    ['code' => 'CON-ENG', 'name' => 'Engineering', 'description' => 'Engineering design and technical services'],
                    ['code' => 'CON-PRC', 'name' => 'Procurement', 'description' => 'Materials and equipment procurement'],
                    ['code' => 'CON-STR', 'name' => 'Stores / Materials', 'description' => 'Materials storage and management'],
                    ['code' => 'CON-FIN', 'name' => 'Finance', 'description' => 'Project finance and accounting'],
                    ['code' => 'CON-HR', 'name' => 'Human Resources', 'description' => 'Workforce and HR management'],
                    ['code' => 'CON-SEC', 'name' => 'Security', 'description' => 'Site security and safety'],
                ],
            ],

            // Security & Uniformed Services Sector
            [
                'sector' => 'security_uniformed_services',
                'departments' => [
                    ['code' => 'SEC-OPS', 'name' => 'Operations', 'description' => 'Security operations and deployment'],
                    ['code' => 'SEC-TRN', 'name' => 'Training', 'description' => 'Personnel training and development'],
                    ['code' => 'SEC-INT', 'name' => 'Intelligence', 'description' => 'Security intelligence and analysis'],
                    ['code' => 'SEC-INV', 'name' => 'Investigations', 'description' => 'Security investigations'],
                    ['code' => 'SEC-LOG', 'name' => 'Logistics', 'description' => 'Equipment and logistics support'],
                    ['code' => 'SEC-HR', 'name' => 'Human Resources', 'description' => 'Personnel management'],
                    ['code' => 'SEC-FIN', 'name' => 'Finance', 'description' => 'Financial management'],
                    ['code' => 'SEC-ADM', 'name' => 'Administration', 'description' => 'Administrative services'],
                    ['code' => 'SEC-ICT', 'name' => 'ICT / Communications', 'description' => 'IT and communications systems'],
                ],
            ],

            // Hospitality, Travel & Tourism Sector
            [
                'sector' => 'hospitality_travel_tourism',
                'departments' => [
                    ['code' => 'HOS-FRO', 'name' => 'Front Office / Reception', 'description' => 'Guest reception and front desk services'],
                    ['code' => 'HOS-HSK', 'name' => 'Housekeeping', 'description' => 'Room cleaning and maintenance'],
                    ['code' => 'HOS-FNB', 'name' => 'Food & Beverage', 'description' => 'Restaurant and catering services'],
                    ['code' => 'HOS-KIT', 'name' => 'Kitchen', 'description' => 'Food preparation and kitchen operations'],
                    ['code' => 'HOS-EVT', 'name' => 'Events / Banquets', 'description' => 'Event planning and banquet services'],
                    ['code' => 'HOS-SPA', 'name' => 'Spa / Recreation', 'description' => 'Spa and recreational facilities'],
                    ['code' => 'HOS-RSV', 'name' => 'Reservations / Sales', 'description' => 'Booking and sales operations'],
                    ['code' => 'HOS-HR', 'name' => 'Human Resources', 'description' => 'Staff management and training'],
                    ['code' => 'HOS-FIN', 'name' => 'Finance', 'description' => 'Financial management and accounting'],
                    ['code' => 'HOS-MNT', 'name' => 'Maintenance', 'description' => 'Facilities maintenance'],
                    ['code' => 'HOS-SEC', 'name' => 'Security', 'description' => 'Guest and property security'],
                ],
            ],

            // NGO, CSO & Donor-Funded Sector
            [
                'sector' => 'ngo_cso_donor_funded',
                'departments' => [
                    ['code' => 'NGO-PRG', 'name' => 'Programs', 'description' => 'Program design and implementation'],
                    ['code' => 'NGO-MEL', 'name' => 'Monitoring, Evaluation & Learning', 'description' => 'Program monitoring and evaluation'],
                    ['code' => 'NGO-FIN', 'name' => 'Finance & Grants', 'description' => 'Financial management and grant administration'],
                    ['code' => 'NGO-HR', 'name' => 'Human Resources', 'description' => 'Staff and volunteer management'],
                    ['code' => 'NGO-ADM', 'name' => 'Administration', 'description' => 'Administrative operations'],
                    ['code' => 'NGO-PRC', 'name' => 'Procurement', 'description' => 'Procurement and logistics'],
                    ['code' => 'NGO-COM', 'name' => 'Communications', 'description' => 'Communications and public relations'],
                    ['code' => 'NGO-ADV', 'name' => 'Advocacy', 'description' => 'Policy advocacy and engagement'],
                    ['code' => 'NGO-ICT', 'name' => 'ICT', 'description' => 'Information technology support'],
                    ['code' => 'NGO-SEC', 'name' => 'Security', 'description' => 'Staff and asset security'],
                ],
            ],

            // Religious Institutions Sector
            [
                'sector' => 'religious_institutions',
                'departments' => [
                    ['code' => 'REL-LED', 'name' => 'Leadership / Clergy Office', 'description' => 'Religious leadership and clergy management'],
                    ['code' => 'REL-ADM', 'name' => 'Administration', 'description' => 'Administrative operations'],
                    ['code' => 'REL-FIN', 'name' => 'Finance & Donations', 'description' => 'Financial management and donation handling'],
                    ['code' => 'REL-SAF', 'name' => 'Safeguarding & Protection', 'description' => 'Child and vulnerable persons protection'],
                    ['code' => 'REL-YTH', 'name' => 'Youth & Community Programs', 'description' => 'Youth ministry and community outreach'],
                    ['code' => 'REL-EVT', 'name' => 'Events & Services', 'description' => 'Religious services and events coordination'],
                    ['code' => 'REL-FAC', 'name' => 'Facilities & Maintenance', 'description' => 'Building and facilities management'],
                    ['code' => 'REL-ICT', 'name' => 'ICT / Media', 'description' => 'IT and media services'],
                    ['code' => 'REL-SEC', 'name' => 'Security', 'description' => 'Premises security'],
                ],
            ],

            // Transport & Logistics Sector
            [
                'sector' => 'transport_logistics',
                'departments' => [
                    ['code' => 'TRN-OPS', 'name' => 'Operations', 'description' => 'Transport and logistics operations'],
                    ['code' => 'TRN-FLT', 'name' => 'Fleet Management', 'description' => 'Vehicle fleet management and maintenance'],
                    ['code' => 'TRN-DRV', 'name' => 'Drivers / Crew', 'description' => 'Driver and crew management'],
                    ['code' => 'TRN-WRH', 'name' => 'Warehouse', 'description' => 'Warehouse and storage operations'],
                    ['code' => 'TRN-DSP', 'name' => 'Dispatch', 'description' => 'Dispatch and route planning'],
                    ['code' => 'TRN-HSE', 'name' => 'Health & Safety', 'description' => 'Transport safety compliance'],
                    ['code' => 'TRN-HR', 'name' => 'Human Resources', 'description' => 'HR and staff management'],
                    ['code' => 'TRN-FIN', 'name' => 'Finance', 'description' => 'Financial management'],
                    ['code' => 'TRN-ICT', 'name' => 'ICT / Tracking', 'description' => 'IT and vehicle tracking systems'],
                    ['code' => 'TRN-SEC', 'name' => 'Security', 'description' => 'Cargo and asset security'],
                ],
            ],

            // Government & Public Sector
            [
                'sector' => 'government_public_sector',
                'departments' => [
                    ['code' => 'GOV-FRO', 'name' => 'Front Office / Service Desk', 'description' => 'Public-facing service delivery'],
                    ['code' => 'GOV-ADM', 'name' => 'Administration', 'description' => 'Administrative operations'],
                    ['code' => 'GOV-FIN', 'name' => 'Finance', 'description' => 'Financial management and budgeting'],
                    ['code' => 'GOV-AUD', 'name' => 'Internal Audit', 'description' => 'Internal auditing and compliance'],
                    ['code' => 'GOV-PRC', 'name' => 'Procurement', 'description' => 'Public procurement'],
                    ['code' => 'GOV-HR', 'name' => 'Human Resources', 'description' => 'Civil service HR management'],
                    ['code' => 'GOV-LEG', 'name' => 'Legal & Compliance', 'description' => 'Legal affairs and regulatory compliance'],
                    ['code' => 'GOV-ICT', 'name' => 'ICT / eGovernment', 'description' => 'IT and digital government services'],
                    ['code' => 'GOV-OPS', 'name' => 'Operations', 'description' => 'Operational activities'],
                    ['code' => 'GOV-SEC', 'name' => 'Security', 'description' => 'Facility and information security'],
                ],
            ],
        ];

        $sortOrder = 0;

        DB::transaction(function () use ($sectors, &$sortOrder) {
            // Clear existing templates
            SectorDepartmentTemplate::query()->forceDelete();

            foreach ($sectors as $sectorData) {
                $sector = $sectorData['sector'];
                $sectorSortOrder = 0;

                foreach ($sectorData['departments'] as $dept) {
                    SectorDepartmentTemplate::create([
                        'sector' => $sector,
                        'department_code' => $dept['code'],
                        'department_name' => $dept['name'],
                        'description' => $dept['description'] ?? null,
                        'status' => true,
                        'sort_order' => $sectorSortOrder++,
                    ]);
                    $sortOrder++;
                }
            }
        });

        $this->command->info("Seeded {$sortOrder} department templates across " . count($sectors) . " sectors.");
    }
}
