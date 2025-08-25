<?php

namespace Tests\Action\Mock;

class ItemMock
{
    public object $data;
    public object $row;
    
    public function __construct(array $properties = [])
    {
        $defaults = [
            'name' => 'Test Item',
            'subtype' => 'melee',
            'emplacement' => 'main1'
        ];
        
        $properties = array_merge($defaults, $properties);
        
        $this->data = (object) $properties;
        $this->row = (object) ['name' => $properties['name']];
    }
    
    public function is_crafted_with($material): bool
    {
        return isset($this->data->craftedWith) && 
               in_array($material, $this->data->craftedWith);
    }
}