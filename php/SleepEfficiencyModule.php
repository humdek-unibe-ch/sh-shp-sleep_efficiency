<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/. */
?>
<?php

require_once __DIR__ . "/../../../component/BaseModel.php";

/**
 * This class is used to prepare all data related to the asset components such
 * that the data can easily be displayed in the view of the component.
 */
class SleepEfficiencyModule extends BaseModel
{

    /** Constants *************************************************************/

    const TIB = 'TIB'; // if you want to save TIB
    const TIB_1 = 'TIB_1'; // required name for formInput field
    const TIB_2 = 'TIB_2'; // required name for formInput field
    const TST = 'TST'; // required name for formInput field
    const SE = 'SE'; // required name for formInput field
    const SE_P = 'SE_P'; // if you want to save percent field
    const SE_3 = 'SE_3'; // required name for formInput field
    const SE_3_P = 'SE_3_P'; // if you want to save percent field
    const debug_log = 'debug_log'; // if you want to check the debug log create such field
    const entry_date = 'entry_date'; // required name for formInput field

    /* Private Properties *****************************************************/

    /**
     * Section_id of the trigger
     */
    private $section_id;

    /* Constructors ***********************************************************/

    /**
     * The constructor.
     *
     * @param array $services
     *  An associative array holding the differnt available services. See the
     *  class definition BasePage for a list of all services.
     * @param int $section_id
     * Section_id of the trigger
     */
    public function __construct($services, $section_id)
    {
        parent::__construct($services);
        $this->section_id = $section_id;
    }

    /* Private Methods ***********************************************************/

    /**
     * Validate required fields and check if they are set in the POST data
     * @retval array 
     * Returns error mssgs in array if there are some
     */
    private function validate()
    {
        $res = [];
        if (!isset($_POST[SleepEfficiencyModule::TIB_1])) {
            $res[] = 'TIB_1 is not set!';
        }
        if (!isset($_POST[SleepEfficiencyModule::TIB_2])) {
            $res[] = 'TIB_2 is not set!';
        }
        if (!isset($_POST[SleepEfficiencyModule::TST])) {
            $res[] = 'TST is not set!';
        }
        return $res;
    }

    /**
     * Get the previous record for the user and current form
     * @param int $form_id the id of the form that hold the results
     * @param string $entry_date The entry date of the sleep diary date
     * @retval array Sql array result
     */
    private function get_previos_records($form_id, $entry_date)
    {
        $filter = "AND entry_date < '" . $entry_date . "' ORDER BY entry_date DESC";
        $sql = 'CALL get_form_data_for_user_with_filter(' . $form_id . ', ' . $_SESSION['id_user'] . ', "' . $filter . '")';
        return $this->db->query_db($sql);
    }

    /**
     * Calculate difference between 2 dates and returns hours
     * @param string $time1 
     * first data
     * @param string $time2
     * second date
     * @retval double 
     * Return the hours between both dates
     */
    private function calc_time_diff($time1, $time2)
    {
        $diff = (strtotime($time2) - strtotime($time1)) / 3600;
        return $diff < 0 ? (24 + $diff) : $diff;
    }

    /**
     * Get the parent of the section
     * @param int $child
     * The id of the child
     * @retval int 
     * Return the id of the parent, in this case the form in which we setup a trigger
     */
    private function get_parent($child)
    {
        $sql = "SELECT parent
                FROM sections_hierarchy
                WHERE child = :child";
        $sql_result = $this->db->query_db_first($sql, array(":child" => $child));
        return $sql_result ? $sql_result['parent'] : false;
    }

    /* Public Methods *********************************************************/

    /**
     * Calculate sleep efficiency and save the result in the POST variable. Later the data will be saved when the formUserInput is run
     */
    public function calc_sleep_efficiency()
    {
        $validation_result = $this->validate();
        $values = array(
            SleepEfficiencyModule::debug_log => json_encode($validation_result, JSON_HEX_APOS | JSON_HEX_QUOT, 10)
        );
        if (count($validation_result) == 0) {
            $values[SleepEfficiencyModule::TIB] = $this->calc_time_diff($_POST[SleepEfficiencyModule::TIB_1]['value'], $_POST[SleepEfficiencyModule::TIB_2]['value']);
            if ($values[SleepEfficiencyModule::TIB] != 0) {
                $values[SleepEfficiencyModule::SE] = ($_POST[SleepEfficiencyModule::TST]['value'] / $values[SleepEfficiencyModule::TIB]) * 100;
                $values[SleepEfficiencyModule::SE_P] = round($values[SleepEfficiencyModule::SE], 0) . '%';
            }
            $previos_records = $this->get_previos_records($this->get_parent($this->section_id), $_POST[SleepEfficiencyModule::entry_date]['value']);
            if (count($previos_records) >= 2) {
                // there are at least 2 values
                $last_2_days_TIB = 0;
                $last_2_days_TST = 0;
                $calc_sleep_efficiency = true;
                for ($i = 0; $i < 2; $i++) {
                    $last_2_days_TIB = $last_2_days_TIB + $this->calc_time_diff($previos_records[$i][SleepEfficiencyModule::TIB_1], $previos_records[$i][SleepEfficiencyModule::TIB_2]);
                    $last_2_days_TST = $last_2_days_TST + $previos_records[$i][SleepEfficiencyModule::TST];
                    $day_difference = (strtotime($_POST[SleepEfficiencyModule::entry_date]['value']) - strtotime($previos_records[$i][SleepEfficiencyModule::entry_date])) / (60 * 60 * 24);
                    if ($day_difference > 5) {
                        // dont calculate average sleep efficiency for the last 3 days if there is a difference of 5 days
                        $calc_sleep_efficiency = false;
                    }
                    if ($previos_records[$i][SleepEfficiencyModule::SE_3]) {
                        // dont calculate average sleep efficiency unless it is a new 3 date period. It is calculated for every third entry
                        $calc_sleep_efficiency = false;
                    }
                }
                if ($calc_sleep_efficiency) {
                    $values[SleepEfficiencyModule::SE_3] = ((($last_2_days_TST + $_POST[SleepEfficiencyModule::TST]['value']) / 3) / (($last_2_days_TIB + $values[SleepEfficiencyModule::TIB]) / 3)) * 100;
                    $values[SleepEfficiencyModule::SE_3_P] = round($values[SleepEfficiencyModule::SE_3], 0) . '%';
                }
            }
        }
        foreach ($values as $field => $value) {
            if (isset($_POST[$field])) {
                $_POST[$field]['value'] = $value;
            }
        }
    }
}
