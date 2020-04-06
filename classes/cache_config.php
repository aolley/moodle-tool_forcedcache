<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

class tool_forcedcache_cache_config extends cache_config {

    //FOR NOW RETURN TRUE, LATER CHECK IF CONFIG TEMPLATE (or whatever) IS SETUP CORRECTLY
    //public static function config_file_exists() {return true;}

    //THIS IS THE JUICE. THIS WILL RETURN THE CONFIG WE SET FROM RULESETS ETC.
    // MANY MORE FUNCTIONS WILL BE CALLED FROM HERE TO SETUP THE CONFIG

    // INITIAL TESTING, POINT TO EXAMPLE FILE
    protected function include_configuration() {
        include(__DIR__.'/../config.php');
        try {
            $this->generate_config_array();
        } catch(Exception $e) {
        }
        return $configuration;
    }

    private function generate_config_array() {
        // READFILE
        $config = $this->read_config_file();
        // GENERATE STORES CONFIG
        $stores = $this->generate_store_instance_config($config['stores']);

        //GENERATE MODE MAPPINGS
        $modemappings = $this->generate_mode_mapping($stores, $config['rules']);

        // GENERATE DEFINITIONS FROM RULESETS

        // GENERATE SITEIDENTIFIER
    }

    // TODO safety around the reads
    private function read_config_file() {
        $filedata = file_get_contents(__DIR__.'/../config.json');
        return json_decode($filedata, true);
    }

    // TODO, if no store exists for a mode, use the configured default.
    private function generate_store_instance_config($stores) {
        $storesarr = array();
        foreach ($stores as $name => $store) {
            $storearr = array();
            $storearr['name'] = $name;
            $storearr['plugin'] = $store['type'];
            // Assume all configuration is correct.
            // If anything borks, we will fallback to core caching.
            $storearr['configuration'] = $store['config'];

            $classname = 'cachestore_'.$store['type'];
            $storearr['class'] = $classname;

            // Now for the derived config from the store information provided.
            // Manually require the cache/lib.php file to get cache classes.
            $cachepath = __DIR__.'/../../../../cache/stores/' . $store['type'] . '/lib.php';
            require_once($cachepath);
            $storearr['features'] = $classname::get_supported_features();
            $storearr['modes'] = $classname::get_supported_modes();

            // Handle default in a separate case
            $storearr['default'] = 'false';

            // Mappingsonly enabled as we will be using rulesets to bind all.
            $storearr['mappingsonly'] = 'false';

            // Force default locking... For now... (how mysterious).
            $storearr['lock'] = 'cachelock_file_default';

            $storesarr[$name] = $storearr;
        }
        return $storesarr;
    }

    private function generate_mode_mapping($stores, $rules) {
        // Here we must decide on how the stores are going to be used
        $modemappings = array();


        // Check all 3 modes sequentially.
        // TODO Check mode is supported by store and exception.
        // TODO Check store exists before mapping it.
        // TODO Ensure sorting isnt borked. Shouldnt matter, as we will explicitly bind it.
        // TODO Ensure config.json is properly formed/ordered (indexes)

        $sort = 0;
        $modemappings = array_merge($modemappings,
            $this->create_mappings($rules['application'], cache_store::MODE_APPLICATION, $sort));
        $sort = count($modemappings);

        // Now do the exact same for Session.
        $modemappings = array_merge($modemappings,
            $this->create_mappings($rules['session'], cache_store::MODE_SESSION, $sort));
        $sort += count($modemappings);

        // Finally for Request.
        $modemappings = array_merge($modemappings,
            $this->create_mappings($rules['request'], cache_store::MODE_REQUEST, $sort));
        $sort += count($modemappings);

        return $modemappings;
    }

    private function create_mappings($rules, $mode, $sortstart) {
        $mappedstores = array();
        $sort = $sortstart;
        foreach ($rules['local'] as $key => $mapping) {
            // Create the mapping.
            $maparr = [];
            $maparr['mode'] = $mode;
            $maparr['store'] = $mapping;
            $maparr['sort'] = $sort;
            $modemappings[$sort] = $maparr;
            $sort++;

            // Now store the mapping name and mode to prevent duplication.
            $mappedstores[] = $mapping;
        }

        // Now we construct the non-locals, after checking they aren't already mapped.
        foreach ($rules['non-local'] as $key => $mapping) {
            if (in_array($mapping, $mappedstores)) {
                continue;
            }

            // Create the mapping.
            $maparr = [];
            $maparr['mode'] = $mode;
            $maparr['store'] = $mapping;
            $maparr['sort'] = $sort;
            $modemappings[$sort] = $maparr;
            $sort++;

            // Now store the mapping name and mode to prevent duplication.
            $mappedstores[] = $mapping;
        }

        return $modemappings;
    }
}
