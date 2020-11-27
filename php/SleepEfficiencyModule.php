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
    const TST = 'TST'; // if you want to save TST
    const TST_1 = 'TST_1'; // required name for formInput field
    const TST_2 = 'TST_2'; // required name for formInput field
    const SE = 'SE'; // required name for formInput field
    const SE_3 = 'SE_3'; // required name for formInput field
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
        if (!isset($_POST[SleepEfficiencyModule::TST_1])) {
            $res[] = 'TST_1 is not set!';
        }
        if (!isset($_POST[SleepEfficiencyModule::TST_2])) {
            $res[] = 'TST_2 is not set!';
        }
        return $res;
    }

    /**
     * Get the previous record for the user and current form
     * @param int $form_id the id of the form that hold the results
     * @retval array Sql array result
     */
    private function get_previos_records($form_id)
    {
        $sql = 'CALL get_form_data_for_user(' . $form_id . ', ' . $_SESSION['id_user'] . ')';
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
        return round(abs(strtotime($time2) - strtotime($time1)) / 3600, 2);
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
            $values[SleepEfficiencyModule::TST] = $this->calc_time_diff($_POST[SleepEfficiencyModule::TST_1]['value'], $_POST[SleepEfficiencyModule::TST_2]['value']);
            if ($values[SleepEfficiencyModule::TIB] != 0) {
                $values[SleepEfficiencyModule::SE] = ($values[SleepEfficiencyModule::TST] / $values[SleepEfficiencyModule::TIB]) * 100;
            }
            $previos_records = $this->get_previos_records($this->get_parent($this->section_id));
            if (count($previos_records) >= 2) {
                // there are at least 2 values
                array_multisort(array_column($previos_records, SleepEfficiencyModule::entry_date), SORT_DESC, $previos_records);
                $last_2_days_TIB = 0;
                $last_2_days_TST = 0;
                for ($i = 0; $i < 2; $i++) {
                    $last_2_days_TIB = $last_2_days_TIB + $this->calc_time_diff($previos_records[$i][SleepEfficiencyModule::TIB_1], $previos_records[$i][SleepEfficiencyModule::TIB_2]);
                    $last_2_days_TST = $last_2_days_TST + $this->calc_time_diff($previos_records[$i][SleepEfficiencyModule::TST_1], $previos_records[$i][SleepEfficiencyModule::TST_2]);
                }
                $values[SleepEfficiencyModule::SE_3] = ((($last_2_days_TST + $values[SleepEfficiencyModule::TST]) / 3) / (($last_2_days_TIB + $values[SleepEfficiencyModule::TIB]) / 3)) * 100;
            }
        }
        foreach ($values as $field => $value) {
            if (isset($_POST[$field])) {
                $_POST[$field]['value'] = $value;
            }
        }
    }
}
