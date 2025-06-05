<?php

namespace HospitalManager\Database\Seeders;

class LabTestCategorySeeder extends Seeder
{
    /**
     * Laboratory categories
     * 
     * @var array
     */
    protected $categories = [
        [
            'name' => 'Hematology',
            'description' => 'Tests related to blood cells and blood disorders',
            'display_order' => 1
        ],
        [
            'name' => 'Clinical Chemistry',
            'description' => 'Analysis of body fluids for diagnostic and therapeutic purposes',
            'display_order' => 2
        ],
        [
            'name' => 'Microbiology',
            'description' => 'Study of microorganisms such as bacteria, viruses, parasites and fungi',
            'display_order' => 3
        ],
        [
            'name' => 'Immunology',
            'description' => 'Study of the immune system and associated disorders',
            'display_order' => 4
        ],
        [
            'name' => 'Endocrinology',
            'description' => 'Analysis of hormones and endocrine system disorders',
            'display_order' => 5
        ],
        [
            'name' => 'Urinalysis',
            'description' => 'Analysis of urine and related disorders',
            'display_order' => 6
        ],
        [
            'name' => 'Toxicology',
            'description' => 'Detection of drugs, toxins and poisons',
            'display_order' => 7
        ],
        [
            'name' => 'Serology',
            'description' => 'Study of serum for antibody detection',
            'display_order' => 8
        ],
        [
            'name' => 'Molecular Diagnostics',
            'description' => 'Analysis of DNA and RNA for disease detection',
            'display_order' => 9
        ],
        [
            'name' => 'Coagulation Studies',
            'description' => 'Analysis of blood clotting disorders',
            'display_order' => 10
        ]
    ];
    
    /**
     * Run the seeder
     */
    public function run()
    {
        $this->log('Seeding laboratory categories...');
        
        global $wpdb;
        $table = $wpdb->prefix . 'hm_lab_categories';
        $count = 0;
        
        foreach ($this->categories as $category) {
            // Check if category already exists
            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name = %s", $category['name'])
            );
            
            if (!$exists) {
                $wpdb->insert($table, [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'display_order' => $category['display_order'],
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]);
                $count++;
            }
        }
        
        $this->log("Created {$count} laboratory categories", 'success');
    }
}