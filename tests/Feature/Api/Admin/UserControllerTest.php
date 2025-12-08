<?php

namespace Tests\Feature\Api\Admin;

use App\Mail\UserInvitation;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Branch $branch;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company, branch, and department
        $this->company = Company::create([
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'contact' => '+255123456789',
            'address' => 'Test Address',
            'status' => true,
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Main Branch',
            'location' => 'Dar es Salaam',
            'address' => 'Main Branch Address',
            'status' => true,
        ]);

        $this->department = Department::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'IT Department',
            'email' => 'it@company.com',
            'contact' => '+255123456791',
            'status' => true,
        ]);

        // Create admin user for authentication
        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@safevoice.tz',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'status' => 'active',
            'is_verified' => true,
        ]);
    }

    /** @test */
    public function it_can_create_admin_user_and_send_invitation_email()
    {
        Mail::fake();
        Sanctum::actingAs($this->adminUser);

        $userData = [
            'name' => 'John Admin',
            'email' => 'john.admin@safevoice.tz',
            'role' => 'admin',
            'phone' => '+255123456792',
            'employee_id' => 'EMP001',
        ];

        $response = $this->postJson('/api/admin/users', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User created successfully and invitation sent.',
            ]);

        // Verify user was created
        $user = User::where('email', 'john.admin@safevoice.tz')->first();
        $this->assertNotNull($user);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals('pending', $user->status);
        $this->assertNotNull($user->invitation_token);
        $this->assertNotNull($user->invitation_expires_at);

        // Verify email was sent
        Mail::assertSent(UserInvitation::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id;
        });
    }

    /** @test */
    public function it_can_create_company_user_and_send_invitation_email()
    {
        Mail::fake();
        Sanctum::actingAs($this->adminUser);

        $userData = [
            'name' => 'Jane User',
            'email' => 'jane.user@company.com',
            'role' => 'user',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'phone' => '+255123456793',
            'employee_id' => 'EMP002',
        ];

        $response = $this->postJson('/api/admin/users', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User created successfully and invitation sent.',
            ]);

        // Verify user was created
        $user = User::where('email', 'jane.user@company.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('user', $user->role);
        $this->assertEquals($this->company->id, $user->company_id);
        $this->assertEquals($this->branch->id, $user->branch_id);
        $this->assertEquals($this->department->id, $user->department_id);

        // Verify email was sent
        Mail::assertSent(UserInvitation::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id;
        });
    }

    /** @test */
    public function it_validates_required_fields_for_user_creation()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'role']);
    }

    /** @test */
    public function it_validates_company_fields_for_company_users()
    {
        Sanctum::actingAs($this->adminUser);

        $userData = [
            'name' => 'Company User',
            'email' => 'user@company.com',
            'role' => 'user', // Company user role
            // Missing company_id, branch_id, department_id
        ];

        $response = $this->postJson('/api/admin/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id', 'branch_id', 'department_id']);
    }

    /** @test */
    public function it_can_list_users_with_relationships()
    {
        Sanctum::actingAs($this->adminUser);

        // Create a test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'user',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'status' => 'active',
            'is_verified' => true,
        ]);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Users retrieved successfully.',
            ]);

        $users = $response->json('data.data');
        $testUser = collect($users)->firstWhere('email', 'test@example.com');

        $this->assertNotNull($testUser);
        $this->assertEquals($this->company->name, $testUser['company']['name']);
        $this->assertEquals($this->branch->name, $testUser['branch']['name']);
        $this->assertEquals($this->department->name, $testUser['department']['name']);
    }

    /** @test */
    public function it_can_resend_invitation_email()
    {
        Mail::fake();
        Sanctum::actingAs($this->adminUser);

        // Create a user with pending invitation
        $user = User::create([
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'role' => 'user',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'status' => 'pending',
            'invitation_token' => 'test-token',
            'invitation_expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson("/api/admin/users/{$user->id}/resend-invitation");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation resent successfully.',
            ]);

        // Verify email was sent
        Mail::assertSent(UserInvitation::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id;
        });

        // Verify token was refreshed
        $user->refresh();
        $this->assertNotEquals('test-token', $user->invitation_token);
    }

    /** @test */
    public function it_can_get_user_statistics()
    {
        Sanctum::actingAs($this->adminUser);

        // Create various users for statistics
        User::create([
            'name' => 'Active User 1',
            'email' => 'active1@example.com',
            'role' => 'user',
            'status' => 'active',
            'company_id' => $this->company->id,
            'is_verified' => true,
        ]);

        User::create([
            'name' => 'Pending User 1',
            'email' => 'pending1@example.com',
            'role' => 'user',
            'status' => 'pending',
            'company_id' => $this->company->id,
        ]);

        $response = $this->getJson('/api/admin/users/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_users',
                    'active_users',
                    'pending_users',
                    'inactive_users',
                    'by_role',
                    'by_company',
                    'recent_registrations',
                ]
            ]);
    }
}
