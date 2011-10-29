<?php

$mysqldump_path = @$argv[1];

$fields_to_obfuscate = array(
    'users' => array(
        'email' => 'email',
        'hashed_password' => 'password',
        'given_name' => 'name',
        'family_name' => 'name',
        'identity_url' => 'url',
        'api_key' => 'password'),
        
    'agents' => array(
        'full_name' => 'name',
        'given_name' => 'name',
        'family_name' => 'name',
        'email' => 'email'),
        
    'contacts' => array(
        'name' => 'name',
        'email' => 'email'),
        
    'content_partner_contacts' => array(
        'full_name' => 'name',
        'given_name' => 'name',
        'family_name' => 'name',
        'email' => 'email',
        'telephone' => 'phone',
        'address' => 'address')
);

$obfuscator = new MysqlDumpObfuscator();
$obfuscator->obfuscate_mysqldump($mysqldump_path, $fields_to_obfuscate);







class FakeDataGenerator
{
    static $alphanumeric = array('A'=>1,'B'=>1,'C'=>1,'D'=>1,'E'=>1,'F'=>1,'G'=>1,'H'=>1,'I'=>1,'J'=>1,'K'=>1,'L'=>1,'M'=>1,
                                 'N'=>1,'O'=>1,'P'=>1,'Q'=>1,'R'=>1,'S'=>1,'T'=>1,'U'=>1,'V'=>1,'W'=>1,'X'=>1,'Y'=>1,'Z'=>1,
                                 'a'=>1,'b'=>1,'c'=>1,'d'=>1,'e'=>1,'f'=>1,'g'=>1,'h'=>1,'i'=>1,'j'=>1,'k'=>1,'l'=>1,'m'=>1,
                                 'n'=>1,'o'=>1,'p'=>1,'q'=>1,'r'=>1,'s'=>1,'t'=>1,'u'=>1,'v'=>1,'w'=>1,'x'=>1,'y'=>1,'z'=>1,
                                 '1'=>1,'2'=>1,'3'=>1,'4'=>1,'5'=>1,'6'=>1,'7'=>1,'8'=>1,'9'=>1,'0');
    // static $alphanumeric_flipped = array_flip(self::$alphanumeric);
    
    public static function email()
    {
        return self::random_characters(rand(3,7)) . '@' . self::random_characters(rand(3,5)) . '.com';
    }
    
    public static function password()
    {
        return self::random_characters(12);
    }
    
    public static function random_characters($length = 5)
    {
        return implode(array_rand(self::$alphanumeric, $length));
    }
    
    public static function __callStatic($function, $args)
    {
        return self::random_characters(rand(3,10));
    }
    
}

class MysqlDumpObfuscator
{
    public function __construct()
    {
        
    }
    
    public function obfuscate_mysqldump($mysqldump_path, $fields_to_obfuscate, $obfuscated_path = null)
    {
        if(!$obfuscated_path)
        {
            $obfuscated_path = dirname($mysqldump_path) . "/obfuscated_dump.txt";
        }
        
        $MYSQLDUMP = fopen($mysqldump_path, 'r');
        $OBFUSCATED = fopen($obfuscated_path, 'w+');
        
        $line_number = 0;
        $this->in_table_definition = false;
        $this->current_table_name = null;
        $this->current_table_fields = null;
        
        while(!feof($MYSQLDUMP))
        {
            if($line = fgets($MYSQLDUMP, 10485760))
            {
                if($line_number % 1000 == 0) echo "line: $line_number\n";
                $line_number++;
                $modified_line = null;
                
                if(preg_match("/^CREATE TABLE `(.*?)`/", $line, $arr))
                {
                    $this->current_table_name = $arr[1];
                    $this->in_table_definition = true;
                    $this->current_table_fields = array();
                    echo "Found table: $this->current_table_name\n";
                }
                
                elseif($this->in_table_definition)
                {
                    if(preg_match("/^ +`(.*?)`/", $line, $arr))
                    {
                        $field_name = $arr[1];
                        $this->current_table_fields[] = $field_name;
                    }else $this->in_table_definition = false;
                }
                
                // we're in a table's INSERT statements and its a table that needs obfuscating
                elseif($this->current_table_name && isset($fields_to_obfuscate[$this->current_table_name]) &&
                  preg_match("/^(INSERT INTO `$this->current_table_name` VALUES )(.*)\n$/", $line, $arr))
                {
                    $values = $arr[2];
                    $modified_line = $arr[1] . $this->obfuscate_line_values($values, $this->current_table_fields, $fields_to_obfuscate[$this->current_table_name]) . "\n";
                }
                
                if($modified_line !== null) fwrite($OBFUSCATED, $modified_line);
                else fwrite($OBFUSCATED, $line);
            }
        }
        
        fclose($OBFUSCATED);
        fclose($MYSQLDUMP);
    }
    
    private function obfuscate_line_values($values, $table_fields, $fields_to_obfuscate)
    {
        // return $values;
        $rows = array();
        $fields = array();
        $characters = str_split($values);
        echo "splitting values\n";
        
        $in_string = false;
        $in_int = false;
        $current_field = '';
        static $chars_to_trim = array(')', ',', ';', '\n');
        $last_char = null;
        
        foreach($characters as $char)
        {
            static $fieldnum = 0;
            $fieldnum++;
            if($fieldnum%100000==0) echo $fieldnum." == ". memory_get_usage() ."\n";
            if(!$in_string && !$in_int)
            {
                if(in_array($char, $chars_to_trim)) continue;
                if($char == '(')
                {
                    if($fields) $rows[] = $fields;
                    // print_r($fields);
                    $fields = array();
                    $current_field = '';
                    // echo "end of row\n";
                    continue;
                }
                
                if($char == "'")
                {
                    $in_string = true;
                    $current_field = $char;
                    $last_char = $char;
                    continue;
                }else
                {
                    $in_int = true;
                    $current_field = $char;
                    continue;
                }
            }
            
            if($in_int)
            {
                if($char == "," || $char == ")")
                {
                    $fields[] = $current_field;
                    $in_int = false;
                    $current_field = '';
                }else
                {
                    $current_field .= $char;
                }
            }
            
            if($in_string)
            {
                if($char == "'" && $last_char != "\\")
                {
                    $fields[] = $current_field . "'";
                    $in_string = false;
                    $current_field = '';
                }else
                {
                    $current_field .= $char;
                }
            }
            
            if($last_char == "\\" && $char == "\\") $last_char = "";
            else $last_char = $char;
        }
        if($fields) $rows[] = $fields;
        
        // turning string indexed array to integer indexed array
        $field_indices_to_obfuscate = array();
        foreach($table_fields as $key => $field_name)
        {
            if(isset($fields_to_obfuscate[$field_name]))
            {
                $field_indices_to_obfuscate[$key] = $fields_to_obfuscate[$field_name];
            }
        }
        
        $field_count = count($table_fields);
        foreach($rows as $key => &$values)
        {
            foreach($field_indices_to_obfuscate as $field_key => $obfuscation_type)
            {
                if($values[$field_key] != "''" && $values[$field_key] != 'NULL') $values[$field_key] = "'" . call_user_func('FakeDataGenerator::' . $obfuscation_type) . "'";
            }
            if(count($values) != $field_count)
            {
                echo "Warning: field count does not match\n";
            }
            $values = "(" . implode($values, ",") . ")";
        }
        
        return implode($rows, ",") . ";";
    }
}



?>