<?php

namespace Scanner;

class RocketMap extends Scanner
{
    public function get_active($eids, $swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
    {
        $conds = array();
        $params = array();

        $select = "pokemon_id, Unix_timestamp(Convert_tz(disappear_time, '+00:00', @@global.time_zone)) AS disappear_time, encounter_id, latitude, longitude, gender, form";
        global $noHighLevelData;
        if (!$noHighLevelData) {
            $select .= ", individual_attack, individual_defense, individual_stamina, move_1, move_2, cp, cp_multiplier";
        }

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng AND disappear_time > :time";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('UTC'));
        $date->setTimestamp(time());
        $params[':time'] = date_format($date, 'Y-m-d H:i:s');

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        if ($tstamp > 0) {
            $date->setTimestamp($tstamp);
            $conds[] = "last_modified > :lastUpdated";
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        }
        if (count($eids)) {
            $pkmn_in = '';
            $i = 1;
            foreach ($eids as $id) {
                $params[':qry_' . $i . "_"] = $id;
                $pkmn_in .= ':qry_' . $i . "_,";
                $i++;
            }
            $pkmn_in = substr($pkmn_in, 0, -1);
            $conds[] = "pokemon_id NOT IN ( $pkmn_in )";
        }

        return $this->query_active($select, $conds, $params);
    }

    public function get_active_by_id($ids, $swLat, $swLng, $neLat, $neLng)
    {
        $conds = array();
        $params = array();

        $select = "pokemon_id, Unix_timestamp(Convert_tz(disappear_time, '+00:00', @@global.time_zone)) AS disappear_time, encounter_id, latitude, longitude, gender, form";
        global $noHighLevelData;
        if (!$noHighLevelData) {
            $select .= ", individual_attack, individual_defense, individual_stamina, move_1, move_2, cp, cp_multiplier";
        }

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng AND disappear_time > :time AND pokemon_id IN ( :ids )";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('UTC'));
        $date->setTimestamp(time());
        $params[':time'] = date_format($date, 'Y-m-d H:i:s');
        if (count($ids)) {
            $pkmn_in = '';
            $i = 1;
            foreach ($ids as $id) {
                $params[':qry_' . $i . "_"] = $id;
                $pkmn_in .= ':qry_' . $i . "_,";
                $i++;
            }
            $pkmn_in = substr($pkmn_in, 0, -1);
            $conds[] = "pokemon_id IN ( $pkmn_in )";
        }

        return $this->query_active($select, $conds, $params);
    }

    public function query_active($select, $conds, $params)
    {
        global $db;

        $query = "SELECT :select
        FROM pokemon 
        WHERE :conditions";

        $query = str_replace(":select", $select, $query);
        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $pokemons = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($pokemons as $pokemon) {
            $pokemon["latitude"] = floatval($pokemon["latitude"]);
            $pokemon["longitude"] = floatval($pokemon["longitude"]);
            $pokemon["disappear_time"] = $pokemon["disappear_time"] * 1000;

            $pokemon["individual_attack"] = isset($pokemon["individual_attack"]) ? intval($pokemon["individual_attack"]) : null;
            $pokemon["individual_defense"] = isset($pokemon["individual_defense"]) ? intval($pokemon["individual_defense"]) : null;
            $pokemon["individual_stamina"] = isset($pokemon["individual_stamina"]) ? intval($pokemon["individual_stamina"]) : null;

            $pokemon["weather_boosted_condition"] = isset($pokemon["weather_boosted_condition"]) ? intval($pokemon["weather_boosted_condition"]) : 0;

            $pokemon["pokemon_id"] = intval($pokemon["pokemon_id"]);
            $pokemon["pokemon_name"] = i8ln($this->data[$pokemon["pokemon_id"]]['name']);
            $pokemon["pokemon_rarity"] = i8ln($this->data[$pokemon["pokemon_id"]]['rarity']);
            $types = $this->data[$pokemon["pokemon_id"]]["types"];
            foreach ($types as $k => $v) {
                $types[$k]['type'] = i8ln($v['type']);
            }
            $pokemon["pokemon_types"] = $types;
            $data[] = $pokemon;

            unset($pokemons[$i]);
            $i++;
        }
        return $data;
    }

    public function get_stops($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0, $lured = false)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }

        if ($lured == "true") {
            $conds[] = "active_fort_modifier IS NOT NULL";
        }

        if ($tstamp > 0) {
            $date = new \DateTime();
            $date->setTimezone(new \DateTimeZone('UTC'));
            $date->setTimestamp($tstamp);
            $conds[] = "last_updated > :lastUpdated";
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        }

        return $this->query_stops($conds, $params);
    }

    public function query_stops($conds, $params)
    {
        global $db;

        $query = "SELECT Unix_timestamp(Convert_tz(lure_expiration, '+00:00', @@global.time_zone)) AS lure_expiration,
        pokestop_id, 
        latitude, 
        longitude 
        FROM pokestop
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $pokestops = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($pokestops as $pokestop) {
            $pokestop["latitude"] = floatval($pokestop["latitude"]);
            $pokestop["longitude"] = floatval($pokestop["longitude"]);
            $pokestop["lure_expiration"] = !empty($pokestop["lure_expiration"]) ? $pokestop["lure_expiration"] * 1000 : null;
            $data[] = $pokestop;

            unset($pokestops[$i]);
            $i++;
        }
        return $data;
    }

    public function get_spawnpoints($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        if ($tstamp > 0) {
            $date = new \DateTime();
            $date->setTimezone(new \DateTimeZone('UTC'));
            $date->setTimestamp($tstamp);
            $conds[] = "last_scanned > :lastUpdated";
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        }

        return $this->query_spawnpoints($conds, $params);
    }

    private function query_spawnpoints($conds, $params)
    {
        global $db;

        $query = "SELECT latitude, 
        longitude, 
        id AS spawnpoint_id,
        latest_seen,
        earliest_unseen,
        links,
        kind
        FROM   spawnpoint
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $spawnpoints = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($spawnpoints as $spawnpoint) {
            $spawnpoint["latitude"] = floatval($spawnpoint["latitude"]);
            $spawnpoint["longitude"] = floatval($spawnpoint["longitude"]);
            $spawnpoint["time"] = $this->get_disappear_time($spawnpoint);
            $data[] = $spawnpoint;

            unset($spawnpoints[$i]);
            $i++;
        }
        return $data;
    }

    private function get_disappear_time($spawnpoint)
    {
        $links = $spawnpoint["links"];

        if ($links == "????") {
            $links = str_replace("?", "s", $spawnpoint["kind"]);
        }
        if (substr_count($links, "-") == 0) {
            $links = substr($links, 0, -1) . '-';
        }
        $links = str_replace("+", "?", $links);
        $links = substr($links, 0, -1) . '-';

        $check = false && $spawnpoint["latest_seen"] != $spawnpoint["earliest_unseen"] ? true : false;
        $no_tth_adjust = $check ? 60 : 0;
        $end = $spawnpoint["latest_seen"] - (3 - strpos($links, "-")) * 900 + $no_tth_adjust;

        return $end % 3600;
    }

    public function get_recent($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('UTC'));
        $conds[] = "last_modified > :lastUpdated";
        if ($tstamp > 0) {
            $date->setTimestamp($tstamp);
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        } else {
            $date->sub(new \DateInterval('PT15M'));
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        }

        return $this->query_recents($conds, $params);
    }

    private function query_recents($conds, $params)
    {
        global $db;

        $query = "SELECT latitude, 
        longitude, 
        Unix_timestamp(Convert_tz(last_modified, '+00:00', @@global.time_zone)) AS last_modified 
        FROM scannedlocation 
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $recents = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($recents as $recent) {
            $recent["latitude"] = floatval($recent["latitude"]);
            $recent["longitude"] = floatval($recent["longitude"]);
            $recent["last_modified"] = $recent["last_modified"] * 1000;
            $data[] = $recent;

            unset($recents[$i]);
            $i++;
        }
        return $data;
    }

    public function get_gyms($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        if ($tstamp > 0) {
            $date = new \DateTime();
            $date->setTimezone(new \DateTimeZone('UTC'));
            $date->setTimestamp($tstamp);
            $conds[] = "last_scanned > :lastUpdated";
            $params[':lastUpdated'] = date_format($date, 'Y-m-d H:i:s');
        }

        return $this->query_gyms($conds, $params);
    }

    public function get_gym($gymId)
    {
        $conds = array();
        $params = array();

        $conds[] = "gym.gym_id = :gymId";
        $params[':gymId'] = $gymId;

        $gyms = $this->query_gyms($conds, $params);
        $gym = $gyms[0];
        $gym["pokemon"] = $this->query_gym_defenders($gymId);
        return $gym;
    }

    public function query_gyms($conds, $params)
    {
        global $db;

        $query = "SELECT gym.gym_id, 
        latitude, 
        longitude, 
        guard_pokemon_id, 
        slots_available, 
        total_cp, 
        Unix_timestamp(Convert_tz(last_modified, '+00:00', @@global.time_zone)) AS last_modified, 
        Unix_timestamp(Convert_tz(gym.last_scanned, '+00:00', @@global.time_zone)) AS last_scanned, 
        team_id, 
        name, 
        level AS raid_level, 
        pokemon_id AS raid_pokemon_id, 
        cp AS raid_pokemon_cp, 
        move_1 AS raid_pokemon_move_1, 
        move_2 AS raid_pokemon_move_2, 
        Unix_timestamp(Convert_tz(start, '+00:00', @@global.time_zone)) AS raid_start, 
        Unix_timestamp(Convert_tz(end, '+00:00', @@global.time_zone)) AS raid_end 
        FROM gym 
        LEFT JOIN gymdetails 
        ON gym.gym_id = gymdetails.gym_id 
        LEFT JOIN raid 
        ON gym.gym_id = raid.gym_id 
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $gyms = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($gyms as $gym) {
            $guard_pid = $gym["guard_pokemon_id"];
            if ($guard_pid == "0") {
                $guard_pid = null;
                $gym["guard_pokemon_id"] = null;
            }
            $raid_pid = $gym["raid_pokemon_id"];
            if ($raid_pid == "0") {
                $raid_pid = null;
                $gym["raid_pokemon_id"] = null;
            }
            $gym["team_id"] = intval($gym["team_id"]);
            $gym["pokemon"] = [];
            $gym["guard_pokemon_name"] = empty($guard_pid) ? null : i8ln($this->data[$guard_pid]["name"]);
            $gym["raid_pokemon_name"] = empty($raid_pid) ? null : i8ln($this->data[$raid_pid]["name"]);
            $gym["latitude"] = floatval($gym["latitude"]);
            $gym["longitude"] = floatval($gym["longitude"]);
            $gym["last_modified"] = $gym["last_modified"] * 1000;
            $gym["last_scanned"] = $gym["last_scanned"] * 1000;
            $gym["raid_start"] = $gym["raid_start"] * 1000;
            $gym["raid_end"] = $gym["raid_end"] * 1000;
            $data[] = $gym;

            unset($gyms[$i]);
            $i++;
        }
        return $data;
    }

    private function query_gym_defenders($gymId)
    {
        global $db;


        $query = "SELECT gymmember.gym_id, 
        pokemon_id, 
        cp AS pokemon_cp, 
        trainer_name, 
        level AS trainer_level, 
        move_1, 
        move_2, 
        iv_attack, 
        iv_defense, 
        iv_stamina 
        FROM gymmember 
        JOIN gympokemon 
        ON gymmember.pokemon_uid = gympokemon.pokemon_uid 
        JOIN trainer 
        ON gympokemon.trainer_name = trainer.name 
        JOIN gym 
        ON gym.gym_id = gymmember.gym_id 
        WHERE gymmember.last_scanned > gym.last_modified 
        AND gymmember.gym_id IN ( :gymId ) 
        GROUP BY name 
        ORDER BY gympokemon.cp DESC";

        $gym_defenders = $db->query($query, [":gymId" => $gymId])->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($gym_defenders as $defender) {
            $pid = $defender["pokemon_id"];
            $defender["pokemon_name"] = i8ln($this->data[$pid]["name"]);

            $defender["iv_attack"] = floatval($defender["iv_attack"]);
            $defender["iv_defense"] = floatval($defender["iv_defense"]);
            $defender["iv_stamina"] = floatval($defender["iv_stamina"]);

            $defender['move_1_name'] = i8ln($this->moves[$defender['move_1']]['name']);
            $defender['move_1_damage'] = $this->moves[$defender['move_1']]['damage'];
            $defender['move_1_energy'] = $this->moves[$defender['move_1']]['energy'];
            $defender['move_1_type']['type'] = i8ln($this->moves[$defender['move_1']]['type']);
            $defender['move_1_type']['type_en'] = $this->moves[$defender['move_1']]['type'];

            $defender['move_2_name'] = i8ln($this->moves[$defender['move_2']]['name']);
            $defender['move_2_damage'] = $this->moves[$defender['move_2']]['damage'];
            $defender['move_2_energy'] = $this->moves[$defender['move_2']]['energy'];
            $defender['move_2_type']['type'] = i8ln($this->moves[$defender['move_2']]['type']);
            $defender['move_2_type']['type_en'] = $this->moves[$defender['move_2']]['type'];

            $data[] = $defender;

            unset($gym_defenders[$i]);
            $i++;
        }
        return $data;
    }

    public function get_gyms_api($swLat, $swLng, $neLat, $neLng)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        global $sendRaidData;
        if (!$sendRaidData) {
            return $this->query_gyms_api($conds, $params);
        } else {
            return $this->query_raids_api($conds, $params);
        }
    }

    public function query_gyms_api($conds, $params)
    {
        global $db;

        $query = "SELECT gym.gym_id, 
        latitude, 
        longitude,
        name
        FROM gym
        LEFT JOIN gymdetails
        ON gym.gym_id = gymdetails.gym_id
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $gyms = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($gyms as $gym) {
            $gym["latitude"] = floatval($gym["latitude"]);
            $gym["longitude"] = floatval($gym["longitude"]);
            $data[] = $gym;

            unset($gyms[$i]);
            $i++;
        }
        return $data;
    }

    public function query_raids_api($conds, $params)
    {
        global $db;

        $query = "SELECT gym.gym_id, 
        latitude, 
        longitude,
        name,
        level AS raid_level, 
        pokemon_id AS raid_pokemon_id, 
        cp AS raid_pokemon_cp, 
        move_1 AS raid_pokemon_move_1, 
        move_2 AS raid_pokemon_move_2, 
        Unix_timestamp(Convert_tz(start, '+00:00', @@global.time_zone)) AS raid_start, 
        Unix_timestamp(Convert_tz(end, '+00:00', @@global.time_zone)) AS raid_end
        FROM gym
        LEFT JOIN gymdetails
        ON gym.gym_id = gymdetails.gym_id
        LEFT JOIN raid 
        ON gym.gym_id = raid.gym_id
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $gyms = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($gyms as $gym) {
            $gym["latitude"] = floatval($gym["latitude"]);
            $gym["longitude"] = floatval($gym["longitude"]);
            $data[] = $gym;

            unset($gyms[$i]);
            $i++;
        }
        return $data;
    }
}
