# 13 - Testing

## Feature Tests com Tenant Isolation

### TenantTestCase Base

```php
<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;

abstract class TenantTestCase extends TestCase
{
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar tenant de teste
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant',
        ]);

        $this->tenant->domains()->create([
            'domain' => 'test-tenant.myapp.test',
            'is_primary' => true,
        ]);

        // Criar user owner
        $this->user = User::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Inicializar tenant context
        tenancy()->initialize($this->tenant);

        // Autenticar user
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }
}
```

### Exemplo: Project Test

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class ProjectTest extends TenantTestCase
{
    /** @test */
    public function user_can_create_project_in_their_tenant()
    {
        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Test description',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_cannot_see_projects_from_other_tenants()
    {
        // Criar outro tenant com projeto
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Verificar que não aparece na listagem
        $response = $this->get('/projects');

        $response->assertDontSee($otherProject->name);

        // Verificar que não pode acessar diretamente
        $response = $this->get("/projects/{$otherProject->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function member_can_only_edit_own_projects()
    {
        // Criar member (não owner)
        $member = User::factory()->create();
        $this->tenant->users()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // Criar projeto de outro user
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Member tenta editar projeto de outro user
        $this->actingAs($member);

        $response = $this->patch("/projects/{$otherProject->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertForbidden();
    }
}
```

### Factory com Tenant

```php
<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => tenancy()->initialized
                ? tenant('id')
                : Tenant::factory(),
            'user_id' => User::factory(),
            'name' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'active',
        ];
    }
}
```

---

## Checklist

- [ ] `TenantTestCase` criado
- [ ] Factories configuradas para tenant
- [ ] Testes de isolamento de dados
- [ ] Testes de autorização por role
- [ ] Testes de limites por plano

---

## Próximo Passo

➡️ **[14-DEPLOYMENT.md](./14-DEPLOYMENT.md)** - Deploy e Produção

---

**Versão:** 1.0
