<?php
namespace Tests\Logs\Mock;

use Tests\Action\Mock\PlayerMock as BasePlayerMock;

class PlayerMock extends BasePlayerMock
{
    private array $options = [];
    public object $coords;

    public function __construct(int $id = 1, string $name = 'MockPlayer')
    {
        parent::__construct($id, $name);
        $this->coords = (object) [
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => 'test_plan'
        ];
        $this->caracs = (object) ['p' => 3]; // Perception par défaut
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function have_option(string $name): int
    {
        return isset($this->options[$name]) && $this->options[$name] ? 1 : 0;
    }

    public function getCoords(bool $refresh = true): object
    {
        return $this->coords;
    }

    public function setCoords(int $x, int $y, int $z, string $plan): void
    {
        $this->coords = (object) [
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'plan' => $plan
        ];
    }

    public function setPerception(int $perception): void
    {
        $this->caracs->p = $perception;
    }
}