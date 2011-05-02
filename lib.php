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

/**
 * @package   mod-choicegroup
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @global int $CHOICEGROUP_COLUMN_HEIGHT */
global $CHOICEGROUP_COLUMN_HEIGHT;
$CHOICEGROUP_COLUMN_HEIGHT = 300;

/** @global int $CHOICEGROUP_COLUMN_WIDTH */
global $CHOICEGROUP_COLUMN_WIDTH;
$CHOICEGROUP_COLUMN_WIDTH = 300;

define('CHOICEGROUP_PUBLISH_ANONYMOUS', '0');
define('CHOICEGROUP_PUBLISH_NAMES',     '1');

define('CHOICEGROUP_SHOWRESULTS_NOT',          '0');
define('CHOICEGROUP_SHOWRESULTS_AFTER_ANSWER', '1');
define('CHOICEGROUP_SHOWRESULTS_AFTER_CLOSE',  '2');
define('CHOICEGROUP_SHOWRESULTS_ALWAYS',       '3');

define('CHOICEGROUP_DISPLAY_HORIZONTAL',  '0');
define('CHOICEGROUP_DISPLAY_VERTICAL',    '1');

/** @global array $CHOICEGROUP_PUBLISH */
global $CHOICEGROUP_PUBLISH;
$CHOICEGROUP_PUBLISH = array (CHOICEGROUP_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'choicegroup'),
                         CHOICEGROUP_PUBLISH_NAMES      => get_string('publishnames', 'choicegroup'));

/** @global array $CHOICEGROUP_SHOWRESULTS */
global $CHOICEGROUP_SHOWRESULTS;
$CHOICEGROUP_SHOWRESULTS = array (CHOICEGROUP_SHOWRESULTS_NOT          => get_string('publishnot', 'choicegroup'),
                         CHOICEGROUP_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'choicegroup'),
                         CHOICEGROUP_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'choicegroup'),
                         CHOICEGROUP_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'choicegroup'));

/** @global array $CHOICEGROUP_DISPLAY */
global $CHOICEGROUP_DISPLAY;
$CHOICEGROUP_DISPLAY = array (CHOICEGROUP_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'choicegroup'),
                         CHOICEGROUP_DISPLAY_VERTICAL     => get_string('displayvertical','choicegroup'));

require_once($CFG->dirroot.'/group/lib.php');

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $choicegroup
 * @return object|null
 */
function choicegroup_user_outline($course, $user, $mod, $choicegroup) {
    global $DB;
    if ($answer = $DB->get_record('choicegroup_answers', array('choicegroupid' => $choicegroup->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(choicegroup_get_option_text($choicegroup, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $choicegroup
 * @return string|void
 */
function choicegroup_user_complete($course, $user, $mod, $choicegroup) {
    global $DB;
    if ($answer = $DB->get_record('choicegroup_answers', array("choicegroupid" => $choicegroup->id, "userid" => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(choicegroup_get_option_text($choicegroup, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        echo get_string("answered", "choicegroup").": $result->info. ".get_string("updated", '', userdate($result->time));
    } else {
        print_string("notanswered", "choicegroup");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $choicegroup
 * @return int
 */
function choicegroup_add_instance($choicegroup) {
    global $DB;

    $choicegroup->timemodified = time();

    if (empty($choicegroup->timerestrict)) {
        $choicegroup->timeopen = 0;
        $choicegroup->timeclose = 0;
    }

    //insert answers
    $choicegroup->id = $DB->insert_record("choicegroup", $choicegroup);
    foreach ($choicegroup->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->choicegroupid = $choicegroup->id;
            if (isset($choicegroup->limit[$key])) {
                $option->maxanswers = $choicegroup->limit[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("choicegroup_options", $option);
        }
    }

    return $choicegroup->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $choicegroup
 * @return bool
 */
function choicegroup_update_instance($choicegroup) {
    global $DB;

    $choicegroup->id = $choicegroup->instance;
    $choicegroup->timemodified = time();


    if (empty($choicegroup->timerestrict)) {
        $choicegroup->timeopen = 0;
        $choicegroup->timeclose = 0;
    }

    //update, delete or insert answers
    foreach ($choicegroup->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->choicegroupid = $choicegroup->id;
        if (isset($choicegroup->limit[$key])) {
            $option->maxanswers = $choicegroup->limit[$key];
        }
        $option->timemodified = time();
        if (isset($choicegroup->optionid[$key]) && !empty($choicegroup->optionid[$key])){//existing choicegroup record
            $option->id=$choicegroup->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("choicegroup_options", $option);
            } else { //empty old option - needs to be deleted.
                $DB->delete_records("choicegroup_options", array("id"=>$option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("choicegroup_options", $option);
            }
        }
    }

    return $DB->update_record('choicegroup', $choicegroup);

}

/**
 * @global object
 * @param object $choicegroup
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function choicegroup_prepare_options($choicegroup, $user, $coursemodule, $allresponses) {
    global $DB;

    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = true;
    $context = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

    foreach ($choicegroup->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = $text;
            $option->maxanswers = $choicegroup->maxanswers[$optionid];
            $option->displaylayout = $choicegroup->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('choicegroup_answers', array('choicegroupid' => $choicegroup->id, 'userid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $choicegroup->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/choicegroup:choose'); //only enrolled users are allowed to make a choicegroup

    if ($choicegroup->allowupdate && $DB->record_exists('choicegroup_answers', array('choicegroupid'=> $choicegroup->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    return $cdisplay;
}

/**
 * @global object
 * @param int $formanswer
 * @param object $choicegroup
 * @param int $userid
 * @param object $course Course object
 * @param object $cm
 */
function choicegroup_user_submit_response($formanswer, $choicegroup, $userid, $course, $cm) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $current = $DB->get_record('choicegroup_answers', array('choicegroupid' => $choicegroup->id, 'userid' => $userid));
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $countanswers=0;
    if($choicegroup->limitanswers) {
        // Find out whether groups are being used and enabled
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }
        if($currentgroup) {
            // If groups are being used, retrieve responses only for users in
            // current group
            global $CFG;
            $answers = $DB->get_records_sql("
SELECT
    ca.*
FROM
    {choicegroup_answers} ca
    INNER JOIN {groups_members} gm ON ca.userid=gm.userid
WHERE
    optionid=?
    AND gm.groupid=?", array($formanswer, $currentgroup));
        } else {
            // Groups are not used, retrieve all answers for this option ID
            $answers = $DB->get_records("choicegroup_answers", array("optionid" => $formanswer));
        }

        if ($answers) {
            foreach ($answers as $a) { //only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/choicegroup:choose')) {
                    $countanswers++;
                }
            }
        }
        $maxans = $choicegroup->maxanswers[$formanswer];
    }

    if (!($choicegroup->limitanswers && ($countanswers >= $maxans) )) {
        if ($current) {

            $newanswer = $current;
            $newanswer->optionid = $formanswer;
            $newanswer->timemodified = time();
            // TODO : begin
            $old_answer = $DB->get_record("choicegroup_answers", array('choicegroupid' => $choicegroup->id, 'userid' => $userid));
            $old_option = $DB->get_record("choicegroup_options", array('id' => $old_answer->optionid));
            groups_remove_member($old_option->text, $userid);
            $new_option = $DB->get_record("choicegroup_options", array('id' => $formanswer));
            groups_add_member($new_option->text, $userid);
            // TODO : end
            $DB->update_record("choicegroup_answers", $newanswer);
            add_to_log($course->id, "choicegroup", "choose again", "view.php?id=$cm->id", $choicegroup->id, $cm->id);
        } else {
            $newanswer = NULL;
            $newanswer->choicegroupid = $choicegroup->id;
            $newanswer->userid = $userid;
            $newanswer->optionid = $formanswer;
            $newanswer->timemodified = time();
            $DB->insert_record("choicegroup_answers", $newanswer);
            // TODO : begin
            $new_option = $DB->get_record("choicegroup_options", array('id' => $formanswer));
            groups_add_member($new_option->text, $userid);
            // TODO : end

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $choicegroup->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
            add_to_log($course->id, "choicegroup", "choose", "view.php?id=$cm->id", $choicegroup->id, $cm->id);
        }
    } else {
        if (!($current->optionid==$formanswer)) { //check to see if current choicegroup already selected - if not display error
            print_error('choicegroupfull', 'choicegroup');
        }
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function choicegroup_show_reportlink($user, $cm) {
    $responsecount =0;
    foreach($user as $optionid => $userlist) {
        if ($optionid) {
            $responsecount += count($userlist);
        }
    }

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "choicegroup", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $choicegroup
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_choicegroup_show_results($choicegroup, $course, $cm, $allresponses, $forcepublish=false) {
    global $CFG, $CHOICEGROUP_COLUMN_HEIGHT, $FULLSCRIPT, $PAGE, $OUTPUT, $DB;

    $display = clone($choicegroup);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    //overwrite options value;
    $display->options = array();
    $totaluser = 0;
    foreach ($choicegroup->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = $optiontext;
        $display->options[$optionid]->maxanswer = $choicegroup->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $totaluser += count($allresponses[$optionid]);
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = $totaluser;
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $display->viewresponsecapability = has_capability('mod/choicegroup:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/choicegroup:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"));
        return false;
    }


    $totalresponsecount = 0;
    foreach ($allresponses as $optionid => $userlist) {
        if ($choicegroup->showunanswered || $optionid) {
            $totalresponsecount += count($userlist);
        }
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $hascapfullnames = has_capability('moodle/site:viewfullnames', $context);

    $viewresponses = has_capability('mod/choicegroup:readresponses', $context);
    switch ($forcepublish) {
        case CHOICEGROUP_PUBLISH_NAMES:
            echo '<div id="tablecontainer">';
            if ($viewresponses) {
                echo '<form id="attemptsform" method="post" action="'.$FULLSCRIPT.'" onsubmit="var menu = document.getElementById(\'menuaction\'); return (menu.options[menu.selectedIndex].value == \'delete\' ? \''.addslashes_js(get_string('deleteattemptcheck','quiz')).'\' : true);">';
                echo '<div>';
                echo '<input type="hidden" name="id" value="'.$cm->id.'" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                echo '<input type="hidden" name="mode" value="overview" />';
            }

            echo "<table cellpadding=\"5\" cellspacing=\"10\" class=\"results names\">";
            echo "<tr>";

            $columncount = array(); // number of votes in each column
            if ($choicegroup->showunanswered) {
                $columncount[0] = 0;
                echo "<th class=\"col0 header\" scope=\"col\">";
                print_string('notanswered', 'choicegroup');
                echo "</th>";
            }
            $count = 1;
            foreach ($choicegroup->option as $optionid => $optiontext) {
                $columncount[$optionid] = 0; // init counters
                echo "<th class=\"col$count header\" scope=\"col\">";
                echo format_string($optiontext);
                echo "</th>";
                $count++;
            }
            echo "</tr><tr>";

            if ($choicegroup->showunanswered) {
                echo "<td class=\"col$count data\" >";
                // added empty row so that when the next iteration is empty,
                // we do not get <table></table> error from w3c validator
                // MDL-7861
                echo "<table class=\"choicegroupresponse\"><tr><td></td></tr>";
                if (!empty($allresponses[0])) {
                    foreach ($allresponses[0] as $user) {
                        echo "<tr>";
                        echo "<td class=\"picture\">";
                        echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                        echo "</td><td class=\"fullname\">";
                        echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                        echo fullname($user, $hascapfullnames);
                        echo "</a>";
                        echo "</td></tr>";
                    }
                }
                echo "</table></td>";
            }
            $count = 1;
            foreach ($choicegroup->option as $optionid => $optiontext) {
                    echo '<td class="col'.$count.' data" >';

                    // added empty row so that when the next iteration is empty,
                    // we do not get <table></table> error from w3c validator
                    // MDL-7861
                    echo '<table class="choicegroupresponse"><tr><td></td></tr>';
                    if (isset($allresponses[$optionid])) {
                        foreach ($allresponses[$optionid] as $user) {
                            $columncount[$optionid] += 1;
                            echo '<tr><td class="attemptcell">';
                            if ($viewresponses and has_capability('mod/choicegroup:deleteresponses',$context)) {
                                echo '<input type="checkbox" name="attemptid[]" value="'. $user->id. '" />';
                            }
                            echo '</td><td class="picture">';
                            echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                            echo '</td><td class="fullname">';
                            echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                            echo fullname($user, $hascapfullnames);
                            echo '</a>';
                            echo '</td></tr>';
                       }
                    }
                    $count++;
                    echo '</table></td>';
            }
            echo "</tr><tr>";
            $count = 1;

            if ($choicegroup->showunanswered) {
                echo "<td></td>";
            }

            foreach ($choicegroup->option as $optionid => $optiontext) {
                echo "<td align=\"center\" class=\"col$count count\">";
                if ($choicegroup->limitanswers) {
                    echo get_string("taken", "choicegroup").":";
                    echo $columncount[$optionid];
                    echo "<br/>";
                    echo get_string("limit", "choicegroup").":";
                    echo $choicegroup->maxanswers[$optionid];
                } else {
                    if (isset($columncount[$optionid])) {
                        echo $columncount[$optionid];
                    }
                }
                echo "</td>";
                $count++;
            }
            echo "</tr>";

            /// Print "Select all" etc.
            if ($viewresponses and has_capability('mod/choicegroup:deleteresponses',$context)) {
                echo '<tr><td></td><td>';
                echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('selectall').'</a> / ';
                echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('deselectall').'</a> ';
                echo '&nbsp;&nbsp;';
                echo html_writer::label(get_string('withselected', 'choicegroup'), 'menuaction');
                echo html_writer::select(array('delete' => get_string('delete')), 'action', '', array(''=>get_string('withselectedusers')), array('id'=>'menuaction'));
                $PAGE->requires->js_init_call('M.util.init_select_autosubmit', array('attemptsform', 'menuaction', ''));
                echo '<noscript id="noscriptmenuaction" style="display:inline">';
                echo '<div>';
                echo '<input type="submit" value="'.get_string('go').'" /></div></noscript>';
                echo '</td><td></td></tr>';
            }

            echo "</table></div>";
            if ($viewresponses) {
                echo "</form></div>";
            }
            break;
    }
    return $display;
}

/**
 * @global object
 * @param array $attemptids
 * @param object $choicegroup Choice main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function choicegroup_delete_responses($attemptids, $choicegroup, $cm, $course) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach($attemptids as $num => $attemptid) {
        if(empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('choicegroup_answers', array('choicegroupid' => $choicegroup->id, 'userid' => $attemptid))) {
            // TODO : begin
            $old_answer = $DB->get_record("choicegroup_answers", array('choicegroupid' => $choicegroup->id, 'userid' => $attemptid));
            $old_option = $DB->get_record("choicegroup_options", array('id' => $old_answer->optionid));
            groups_remove_member($old_option->text, $attemptid);
            // TODO : end
            $DB->delete_records('choicegroup_answers', array('choicegroupid' => $choicegroup->id, 'userid' => $attemptid));
            // Update completion state
            if ($completion->is_enabled($cm) && $choicegroup->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $attemptid);
            }
        }
    }
    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function choicegroup_delete_instance($id) {
    global $DB;

    if (! $choicegroup = $DB->get_record("choicegroup", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("choicegroup_answers", array("choicegroupid"=>"$choicegroup->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("choicegroup_options", array("choicegroupid"=>"$choicegroup->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("choicegroup", array("id"=>"$choicegroup->id"))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns the users with data in one choicegroup
 * (users with records in choicegroup_responses, students)
 *
 * @param int $choicegroupid
 * @return array
 */
function choicegroup_get_participants($choicegroupid) {
    global $DB;

    //Get students
    $students = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {user} u,
                                      {choicegroup_answers} a
                                 WHERE a.choicegroupid = ? AND
                                       u.id = a.userid", array($choicegroupid));

    //Return students array (it contains an array of unique users)
    return ($students);
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $choicegroup
 * @param int $id
 * @return string
 */
function choicegroup_get_option_text($choicegroup, $id) {
    global $DB;

    if ($result = $DB->get_record("choicegroup_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "choicegroup");
    }
}

/**
 * Gets a full choicegroup record
 *
 * @global object
 * @param int $choicegroupid
 * @return object|bool The choicegroup or false
 */
function choicegroup_get_choicegroup($choicegroupid) {
    global $DB;

    if ($choicegroup = $DB->get_record("choicegroup", array("id" => $choicegroupid))) {
        if ($options = $DB->get_records("choicegroup_options", array("choicegroupid" => $choicegroupid), "id")) {
            foreach ($options as $option) {
                $choicegroup->option[$option->id] = $option->text;
                $choicegroup->maxanswers[$option->id] = $option->maxanswers;
            }
            return $choicegroup;
        }
    }
    return false;
}

/**
 * @return array
 */
function choicegroup_get_view_actions() {
    return array('view','view all','report');
}

/**
 * @return array
 */
function choicegroup_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the choicegroup.
 *
 * @param object $mform form passed by reference
 */
function choicegroup_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'choicegroupheader', get_string('modulenameplural', 'choicegroup'));
    $mform->addElement('advcheckbox', 'reset_choicegroup', get_string('removeresponses','choicegroup'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function choicegroup_reset_course_form_defaults($course) {
    return array('reset_choicegroup'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * choicegroup responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function choicegroup_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'choicegroup');
    $status = array();

    if (!empty($data->reset_choicegroup)) {
        $choicegroupssql = "SELECT ch.id
                       FROM {choicegroup} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('choicegroup_answers', "choicegroupid IN ($choicegroupssql)", array($data->courseid)); // TODO (à voir...)
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'choicegroup'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('choicegroup', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $choicegroup
 * @param object $cm
 * @param int $groupmode
 * @return array
 */
function choicegroup_get_response_data($choicegroup, $cm, $groupmode) {
    global $CFG, $USER, $DB;

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    $allresponses[0] = get_enrolled_users($context, 'mod/choicegroup:choose', $currentgroup, user_picture::fields('u', array('idnumber')), 'u.lastname ASC,u.firstname ASC');

/// Get all the recorded responses for this choicegroup
    $rawresponses = $DB->get_records('choicegroup_answers', array('choicegroupid' => $choicegroup->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                unset($allresponses[0][$response->userid]);   // Remove from unanswered column
            }
        }
    }
    return $allresponses;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function choicegroup_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function choicegroup_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $choicegroupnode The node to add module settings to
 */
function choicegroup_extend_settings_navigation(settings_navigation $settings, navigation_node $choicegroupnode) {
    global $PAGE;

    if (has_capability('mod/choicegroup:readresponses', $PAGE->cm->context)) {

        $groupmode = groups_get_activity_groupmode($PAGE->cm);
        if ($groupmode) {
            groups_get_activity_group($PAGE->cm, true);
        }
        // We only actually need the choicegroup id here
        $choicegroup = new stdClass;
        $choicegroup->id = $PAGE->cm->instance;
        $allresponses = choicegroup_get_response_data($choicegroup, $PAGE->cm, $groupmode);   // Big function, approx 6 SQL calls per user

        $responsecount =0;
        foreach($allresponses as $optionid => $userlist) {
            if ($optionid) {
                $responsecount += count($userlist);
            }
        }
        $choicegroupnode->add(get_string("viewallresponses", "choicegroup", $responsecount), new moodle_url('/mod/choicegroup/report.php', array('id'=>$PAGE->cm->id)));
    }
}

/**
 * Obtains the automatic completion state for this choicegroup based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function choicegroup_get_completion_state($course, $cm, $userid, $type) {
    global $CFG,$DB;

    // Get choicegroup details
    $choicegroup = $DB->get_record('choicegroup', array('id'=>$cm->instance), '*',
            MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($choicegroup->completionsubmit) {
        return $DB->record_exists('choicegroup_answers', array(
                'choicegroupid'=>$choicegroup->id, 'userid'=>$userid));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}
