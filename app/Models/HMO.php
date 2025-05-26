<?php
namespace HospitalManager\Models;


class HMO extends BaseModel
{
    protected $primaryKey = 'ID';
    protected $tableName = 'hm_hmos';
    protected $fillable = [
        'name'
    ];
    
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        parent::__construct($attributes);
    }

    /**
     * Find an HMO record by ID
     * 
     * @param int $ID The HMO ID
     * @return static|null
     */
    public static function find($ID = 0)
    {
        global $wpdb;
        
        if (empty($ID)) {
            return null;
        }
        
        // Get the table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Add SQL_NO_CACHE to prevent caching issues
        $query = $wpdb->prepare("SELECT SQL_NO_CACHE * FROM {$table} WHERE ID = %d", $ID);
        
        $hmo_data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$hmo_data) {
            return null;
        }
        
        // Create a new HMO instance with the fetched data
        return new static($hmo_data);
    }

    /**
     * Create a new HMO record
     */
    public static function create(array $data)
    {
        global $wpdb;
        
        // Get the table name
        $instance = new static();
        $table = $instance->getTable();
        
        // Set created_at if applicable
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Set updated_at if applicable
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }
        
        try {
            // Ensure data only contains valid column names
            $filtered_data = array_intersect_key($data, array_flip([
                'name', 'created_at', 'updated_at'
            ]));
            
            // Insert the record
            $result = $wpdb->insert(
                $table,
                $filtered_data,
                ['%s', '%s', '%s'] // All fields are strings
            );
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            $filtered_data['ID'] = $wpdb->insert_id;
            
            return new static($filtered_data);
        } catch (\Exception $e) {
            throw new \Exception('Failed to create HMO record: ' . $e->getMessage());
        }
    }
    
    /**
     * Save changes to the database
     */
    public function save()
    {
        global $wpdb;
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        if (isset($this->attributes['ID']) && intval($this->attributes['ID']) > 0) {
            // Prepare the update data
            $update_data = [];
            
            // Only include fields that are in the fillable array
            foreach ($this->fillable as $field) {
                if (isset($this->attributes[$field])) {
                    $update_data[$field] = $this->attributes[$field];
                }
            }
            
            // Add updated_at
            $update_data['updated_at'] = current_time('mysql');
            
            // Use WordPress's update function
            $result = $wpdb->update(
                $table,
                $update_data,
                ['ID' => $this->attributes['ID']],
                null, // Format will be determined automatically
                ['%d'] // ID is an integer
            );
            
            return $result !== false;
        } else {
            return false;
        }
    }
    
    /**
     * Delete the HMO record
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID']) || intval($this->attributes['ID']) <= 0) {
            return false;
        }
        
        // Make sure we have the correct table name
        $table = $wpdb->prefix . $this->tableName;
        
        // Delete the record
        $result = $wpdb->delete(
            $table,
            ['ID' => $this->attributes['ID']],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Relationship with patients
     * 
     * @return array Array of Patient objects
     */
    public function patients()
    {
        global $wpdb;
        
        if (!isset($this->attributes['ID'])) {
            return [];
        }
        
        // Get the Patient model's table
        $patient = new \HospitalManager\Models\Patient();
        $patient_table = $patient->getTable();
        
        // Query patients with this HMO ID
        $query = $wpdb->prepare(
            "SELECT * FROM {$patient_table} WHERE hmo_id = %d",
            $this->attributes['ID']
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Return array of Patient objects
        return array_map(function($data) {
            return new \HospitalManager\Models\Patient($data);
        }, $results ?: []);
    }
}
