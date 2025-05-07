<?php

namespace HospitalManager\Database\Seeders;

class HMOSeeder extends Seeder
{
    protected $hmos = [
        'Hygeia HMO',
        'Avon Healthcare Limited',
        'Novo Health Africa',
        'Axa Mansard Health',
        'Total Health Trust',
        'Reliance HMO',
        'AIICO Multishield',
        'Liberty Health',
        'ICEA LION Life Assurance',
        'Metropolitan Health',
        'Managed Healthcare Services',
        'Clearline HMO',
        'Healthcare International',
        'Songhai Health Trust',
        'EVES Healthcare'
    ];
    
    public function run()
    {
        $table = $this->wpdb->prefix . 'hm_hmos';
        $count = 0;
        
        $this->log("Creating HMOs");
        
        foreach ($this->hmos as $hmo_name) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE name = %s",
                $hmo_name
            ));
            
            if (!$exists) {
                $this->wpdb->insert(
                    $table,
                    [
                        'name' => $hmo_name,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s']
                );
                
                $count++;
            }
        }
        
        $this->log("Created {$count} HMOs");
    }
}
