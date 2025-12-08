<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table - add indexes for common queries
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email_verified_at']);
            $table->index(['created_at']);
            $table->index(['company_id', 'branch_id']);
        });

        // Subscriptions table - add additional indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status']);
            $table->index(['plan_id']);
            $table->index(['starts_on']);
            $table->index(['ends_on']);
            $table->index(['grace_until']);
            $table->index(['auto_renew']);
            $table->index(['cancel_at_period_end']);
            $table->index(['created_at']);
            // Composite indexes for complex queries
            $table->index(['status', 'ends_on']);
            $table->index(['status', 'auto_renew']);
            $table->index(['plan_id', 'status']);
            $table->index(['company_id', 'status', 'ends_on']);
        });

        // Subscription_branch pivot table - add indexes
        Schema::table('subscription_branch', function (Blueprint $table) {
            $table->index(['subscription_id']);
            $table->index(['branch_id']);
            $table->index(['activated_from']);
            $table->index(['activated_until']);
            $table->index(['subscription_id', 'activated_until']);
            $table->index(['branch_id', 'activated_from', 'activated_until']);
        });

        // Payments table - add additional indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status']);
            $table->index(['payment_method']);
            $table->index(['created_at']);
            $table->index(['company_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['subscription_plan_id', 'status']);
        });

        // Investigator_company pivot table - add indexes
        Schema::table('investigator_company', function (Blueprint $table) {
            $table->index(['investigator_id']);
            $table->index(['company_id']);
            $table->index(['created_at']);
        });

        // Companies table - add plan_id index if exists
        if (Schema::hasColumn('companies', 'plan_id')) {
            Schema::table('companies', function (Blueprint $table) {
                // Check if index doesn't already exist before adding
                if (!Schema::hasIndex('companies', ['plan_id', 'status'])) {
                    $table->index(['plan_id', 'status']);
                }
            });
        }

        // Cases table - add additional composite indexes
        Schema::table('cases', function (Blueprint $table) {
            // Only add indexes for columns that exist
            if (Schema::hasColumn('cases', 'branch_id')) {
                $table->index(['branch_id']);
            }
            if (Schema::hasColumn('cases', 'department_id')) {
                $table->index(['department_id']);
            }
            $table->index(['created_at']);
            if (Schema::hasColumn('cases', 'source')) {
                $table->index(['source']);
            }
            if (Schema::hasColumn('cases', 'severity_level')) {
                $table->index(['severity_level']);
            }
            if (Schema::hasColumn('cases', 'follow_up_required')) {
                $table->index(['follow_up_required']);
            }
            // Composite indexes for reporting and filtering
            $table->index(['company_id', 'created_at']);
            if (Schema::hasColumn('cases', 'branch_id')) {
                $table->index(['branch_id', 'status']);
            }
            $table->index(['type', 'status', 'priority']);
            if (Schema::hasColumn('cases', 'assigned_to')) {
                $table->index(['assigned_to', 'status']);
            }
            if (Schema::hasColumn('cases', 'due_date')) {
                $table->index(['status', 'due_date']);
            }
            if (Schema::hasColumn('cases', 'resolved_at')) {
                $table->index(['resolved_at']);
            }
        });

        // Incident categories - add indexes
        if (Schema::hasTable('incident_categories')) {
            Schema::table('incident_categories', function (Blueprint $table) {
                if (!Schema::hasIndex('incident_categories', ['company_id'])) {
                    $table->index(['company_id']);
                }
                if (Schema::hasColumn('incident_categories', 'status') && !Schema::hasIndex('incident_categories', ['status'])) {
                    $table->index(['status']);
                }
                if (Schema::hasColumn('incident_categories', 'name') && !Schema::hasIndex('incident_categories', ['name'])) {
                    $table->index(['name']);
                }
            });
        }

        // Feedback categories - add indexes
        if (Schema::hasTable('feedback_categories')) {
            Schema::table('feedback_categories', function (Blueprint $table) {
                if (!Schema::hasIndex('feedback_categories', ['company_id'])) {
                    $table->index(['company_id']);
                }
                if (Schema::hasColumn('feedback_categories', 'status') && !Schema::hasIndex('feedback_categories', ['status'])) {
                    $table->index(['status']);
                }
                if (Schema::hasColumn('feedback_categories', 'name') && !Schema::hasIndex('feedback_categories', ['name'])) {
                    $table->index(['name']);
                }
            });
        }

        // Case involved parties - add indexes
        if (Schema::hasTable('case_involved_parties')) {
            Schema::table('case_involved_parties', function (Blueprint $table) {
                if (!Schema::hasIndex('case_involved_parties', ['case_id'])) {
                    $table->index(['case_id']);
                }
                if (Schema::hasColumn('case_involved_parties', 'party_type') && !Schema::hasIndex('case_involved_parties', ['party_type'])) {
                    $table->index(['party_type']);
                }
            });
        }

        // Routing rules - add indexes
        if (Schema::hasTable('routing_rules')) {
            Schema::table('routing_rules', function (Blueprint $table) {
                if (!Schema::hasIndex('routing_rules', ['company_id'])) {
                    $table->index(['company_id']);
                }
                if (Schema::hasColumn('routing_rules', 'is_active') && !Schema::hasIndex('routing_rules', ['is_active'])) {
                    $table->index(['is_active']);
                }
                if (Schema::hasColumn('routing_rules', 'priority') && !Schema::hasIndex('routing_rules', ['priority'])) {
                    $table->index(['priority']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_verified_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['company_id', 'branch_id']);
        });

        // Subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['plan_id']);
            $table->dropIndex(['starts_on']);
            $table->dropIndex(['ends_on']);
            $table->dropIndex(['grace_until']);
            $table->dropIndex(['auto_renew']);
            $table->dropIndex(['cancel_at_period_end']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'ends_on']);
            $table->dropIndex(['status', 'auto_renew']);
            $table->dropIndex(['plan_id', 'status']);
            $table->dropIndex(['company_id', 'status', 'ends_on']);
        });

        // Subscription_branch pivot table
        Schema::table('subscription_branch', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['activated_from']);
            $table->dropIndex(['activated_until']);
            $table->dropIndex(['subscription_id', 'activated_until']);
            $table->dropIndex(['branch_id', 'activated_from', 'activated_until']);
        });

        // Payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['subscription_plan_id', 'status']);
        });

        // Investigator_company pivot table
        Schema::table('investigator_company', function (Blueprint $table) {
            $table->dropIndex(['investigator_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['created_at']);
        });

        // Companies table
        if (Schema::hasColumn('companies', 'plan_id')) {
            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasIndex('companies', ['plan_id', 'status'])) {
                    $table->dropIndex(['plan_id', 'status']);
                }
            });
        }

        // Cases table
        Schema::table('cases', function (Blueprint $table) {
            if (Schema::hasColumn('cases', 'branch_id') && Schema::hasIndex('cases', ['branch_id'])) {
                $table->dropIndex(['branch_id']);
            }
            if (Schema::hasColumn('cases', 'department_id') && Schema::hasIndex('cases', ['department_id'])) {
                $table->dropIndex(['department_id']);
            }
            if (Schema::hasIndex('cases', ['created_at'])) {
                $table->dropIndex(['created_at']);
            }
            if (Schema::hasColumn('cases', 'source') && Schema::hasIndex('cases', ['source'])) {
                $table->dropIndex(['source']);
            }
            if (Schema::hasColumn('cases', 'severity_level') && Schema::hasIndex('cases', ['severity_level'])) {
                $table->dropIndex(['severity_level']);
            }
            if (Schema::hasColumn('cases', 'follow_up_required') && Schema::hasIndex('cases', ['follow_up_required'])) {
                $table->dropIndex(['follow_up_required']);
            }
            if (Schema::hasIndex('cases', ['company_id', 'created_at'])) {
                $table->dropIndex(['company_id', 'created_at']);
            }
            if (Schema::hasColumn('cases', 'branch_id') && Schema::hasIndex('cases', ['branch_id', 'status'])) {
                $table->dropIndex(['branch_id', 'status']);
            }
            if (Schema::hasIndex('cases', ['type', 'status', 'priority'])) {
                $table->dropIndex(['type', 'status', 'priority']);
            }
            if (Schema::hasColumn('cases', 'assigned_to') && Schema::hasIndex('cases', ['assigned_to', 'status'])) {
                $table->dropIndex(['assigned_to', 'status']);
            }
            if (Schema::hasColumn('cases', 'due_date') && Schema::hasIndex('cases', ['status', 'due_date'])) {
                $table->dropIndex(['status', 'due_date']);
            }
            if (Schema::hasColumn('cases', 'resolved_at') && Schema::hasIndex('cases', ['resolved_at'])) {
                $table->dropIndex(['resolved_at']);
            }
        });

        // Conditional rollbacks for other tables
        if (Schema::hasTable('incident_categories')) {
            Schema::table('incident_categories', function (Blueprint $table) {
                if (Schema::hasIndex('incident_categories', ['company_id'])) {
                    $table->dropIndex(['company_id']);
                }
                if (Schema::hasColumn('incident_categories', 'status') && Schema::hasIndex('incident_categories', ['status'])) {
                    $table->dropIndex(['status']);
                }
                if (Schema::hasColumn('incident_categories', 'name') && Schema::hasIndex('incident_categories', ['name'])) {
                    $table->dropIndex(['name']);
                }
            });
        }

        if (Schema::hasTable('feedback_categories')) {
            Schema::table('feedback_categories', function (Blueprint $table) {
                if (Schema::hasIndex('feedback_categories', ['company_id'])) {
                    $table->dropIndex(['company_id']);
                }
                if (Schema::hasColumn('feedback_categories', 'status') && Schema::hasIndex('feedback_categories', ['status'])) {
                    $table->dropIndex(['status']);
                }
                if (Schema::hasColumn('feedback_categories', 'name') && Schema::hasIndex('feedback_categories', ['name'])) {
                    $table->dropIndex(['name']);
                }
            });
        }

        if (Schema::hasTable('case_involved_parties')) {
            Schema::table('case_involved_parties', function (Blueprint $table) {
                if (Schema::hasIndex('case_involved_parties', ['case_id'])) {
                    $table->dropIndex(['case_id']);
                }
                if (Schema::hasColumn('case_involved_parties', 'party_type') && Schema::hasIndex('case_involved_parties', ['party_type'])) {
                    $table->dropIndex(['party_type']);
                }
            });
        }

        if (Schema::hasTable('routing_rules')) {
            Schema::table('routing_rules', function (Blueprint $table) {
                if (Schema::hasIndex('routing_rules', ['company_id'])) {
                    $table->dropIndex(['company_id']);
                }
                if (Schema::hasColumn('routing_rules', 'is_active') && Schema::hasIndex('routing_rules', ['is_active'])) {
                    $table->dropIndex(['is_active']);
                }
                if (Schema::hasColumn('routing_rules', 'priority') && Schema::hasIndex('routing_rules', ['priority'])) {
                    $table->dropIndex(['priority']);
                }
            });
        }
    }
};
