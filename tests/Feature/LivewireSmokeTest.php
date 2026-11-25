<?php

namespace Tests\Feature;

use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class PingComponent extends Component
{
    public string $message = 'Khalid Traders Livewire 3 Verified';

    public function render()
    {
        return <<<'HTML'
        <div>
            <span>{{ $message }}</span>
        </div>
        HTML;
    }
}

class LivewireSmokeTest extends TestCase
{
    /**
     * Test minimal Livewire 3 component rendering.
     */
    public function test_livewire_renders_component_successfully(): void
    {
        Livewire::test(PingComponent::class)
            ->assertSee('Khalid Traders Livewire 3 Verified')
            ->assertStatus(200);
    }
}
