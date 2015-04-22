<?php
/* For licensing terms, see /license.txt */

/**
 * The INTRODUCTION MICRO MODULE is used to insert and edit
 * an introduction section on a Chamilo module or on the course homepage.
 * It can be inserted on any Chamilo module, provided the corresponding setting
 * is enabled in the administration section.
 *
 * The introduction content are stored in a table called "tool_intro"
 * in the course Database. Each module introduction has an Id stored in
 * the table, which matches a specific module.
 *
 * '(c_)tool_intro' table description
 *   c_id: int
 *   id : int
 *   intro_text :text
 *   session_id: int
 *
 * usage :
 *
 * $moduleId = 'XX'; // specifying the module tool (string value)
 * include(introductionSection.inc.php);
 *
 * This script is also used since Chamilo 1.9 to show course progress (from the
 * course_progress module)
 *
 * @package chamilo.include
 */

/*  Constants and variables */

$TBL_INTRODUCTION = Database::get_course_table(TABLE_TOOL_INTRO);
$intro_editAllowed = $is_allowed_to_edit;
$session_id = api_get_session_id();

$introduction_section = '';

global $charset;
$intro_cmdEdit = empty($_GET['intro_cmdEdit']) ? '' : $_GET['intro_cmdEdit'];
$intro_cmdUpdate = isset($_POST['intro_cmdUpdate']);
$intro_cmdDel = empty($_GET['intro_cmdDel']) ? '' : $_GET['intro_cmdDel'];
$intro_cmdAdd = empty($_GET['intro_cmdAdd']) ? '' : $_GET['intro_cmdAdd'];
$courseId = api_get_course_id();

if (!empty($courseId)) {
    $form = new FormValidator('introduction_text', 'post', api_get_self().'?'.api_get_cidreq());
} else {
    $form = new FormValidator('introduction_text');
}

$renderer =& $form->defaultRenderer();
$renderer->setCustomElementTemplate('<div style="width: 80%; margin: 0px auto; padding-bottom: 10px; ">{element}</div>');

$toolbar_set = 'IntroductionTool';
$width = '100%';
$height = '300';

$editor_config = array('ToolbarSet' => $toolbar_set, 'Width' => $width, 'Height' => $height);

$form->addHtmlEditor('intro_content', null, null, false, $editor_config);
$form->addButtonSave(get_lang('SaveIntroText'), 'intro_cmdUpdate');

/* INTRODUCTION MICRO MODULE - COMMANDS SECTION (IF ALLOWED) */
$course_id = api_get_course_int_id();

if ($intro_editAllowed) {
    /* Replace command */
    if ($intro_cmdUpdate) {
        if ($form->validate()) {
            $form_values = $form->exportValues();
            $intro_content = Security::remove_XSS(stripslashes(api_html_entity_decode($form_values['intro_content'])), COURSEMANAGERLOWSECURITY);
            if (!empty($intro_content)) {
                $sql = "REPLACE $TBL_INTRODUCTION
                        SET
                            c_id = $course_id, id='".Database::escape_string($moduleId)."',
                            intro_text='".Database::escape_string($intro_content)."',
                            session_id='".intval($session_id)."'
                        ";
                Database::query($sql);
                $introduction_section .= Display::return_message(
                    get_lang('IntroductionTextUpdated'),
                    'confirmation',
                    false
                );
            } else {
                // got to the delete command
                $intro_cmdDel = true;
            }
        } else {
            $intro_cmdEdit = true;
        }
    }

    /* Delete Command */
    if ($intro_cmdDel) {
        $sql = "DELETE FROM $TBL_INTRODUCTION
                WHERE
                    c_id = $course_id AND
                    id='".Database::escape_string($moduleId)."' AND
                    session_id='".intval($session_id)."'";
        Database::query($sql);
        $introduction_section .= Display::return_message(get_lang('IntroductionTextDeleted'), 'confirmation');
    }
}

/* INTRODUCTION MICRO MODULE - DISPLAY SECTION */

/* Retrieves the module introduction text, if exist */
/* @todo use a lib to query the $TBL_INTRODUCTION table */
// Getting course intro
$intro_content = null;
$sql = "SELECT intro_text FROM $TBL_INTRODUCTION
        WHERE c_id = $course_id AND id='".Database::escape_string($moduleId)."' AND session_id = 0";

$intro_dbQuery = Database::query($sql);
if (Database::num_rows($intro_dbQuery) > 0) {
    $intro_dbResult = Database::fetch_array($intro_dbQuery);
    $intro_content = $intro_dbResult['intro_text'];
}

// Getting session intro
if (!empty($session_id)) {
    $sql = "SELECT intro_text FROM $TBL_INTRODUCTION
        WHERE c_id = $course_id AND id='".Database::escape_string($moduleId)."' AND session_id = '".intval($session_id)."'";
    $intro_dbQuery = Database::query($sql);
    $introSessionContent = null;
    if (Database::num_rows($intro_dbQuery) > 0) {
        $intro_dbResult = Database::fetch_array($intro_dbQuery);
        $introSessionContent = $intro_dbResult['intro_text'];
    }
    // If the course session intro exists replace it.
    if (!empty($introSessionContent)) {
        $intro_content = $introSessionContent;
    }
}

/* Determines the correct display */

if ($intro_cmdEdit || $intro_cmdAdd) {
    $intro_dispDefault = false;
    $intro_dispForm = true;
    $intro_dispCommand = false;
} else {
    $intro_dispDefault = true;
    $intro_dispForm = false;

    if ($intro_editAllowed) {
        $intro_dispCommand = true;
    } else {
        $intro_dispCommand = false;
    }
}

/* Executes the display */

// display thematic advance inside a postit
if ($intro_dispForm) {
    $default['intro_content'] = $intro_content;
    $form->setDefaults($default);
    $introduction_section .= '<div id="courseintro" style="width: 98%">';
    $introduction_section .= $form->return_form();
    $introduction_section .= '</div>';
}

$thematic_description_html = '';

if ($tool == TOOL_COURSE_HOMEPAGE && !isset($_GET['intro_cmdEdit'])) {
    // Only show this if we're on the course homepage and we're not currently editing
    $thematic = new Thematic();
    $displayMode = api_get_course_setting('display_info_advance_inside_homecourse');
    $class1 = '';
    if ($displayMode == '1') {
        // Show only the current course progress step
        // $information_title = get_lang('InfoAboutLastDoneAdvance');
        $last_done_advance =  $thematic->get_last_done_thematic_advance();
        $thematic_advance_info = $thematic->get_thematic_advance_list($last_done_advance);
        $subTitle1 = get_lang('CurrentTopic');
        $class1 = ' current';
    } else if($displayMode == '2') {
        // Show only the two next course progress steps
        // $information_title = get_lang('InfoAboutNextAdvanceNotDone');
        $last_done_advance = $thematic->get_next_thematic_advance_not_done();
        $next_advance_not_done = $thematic->get_next_thematic_advance_not_done(2);
        $thematic_advance_info = $thematic->get_thematic_advance_list($last_done_advance);
        $thematic_advance_info2 = $thematic->get_thematic_advance_list($next_advance_not_done);
        $subTitle1 = $subTitle2 = get_lang('NextTopic');
    } else if($displayMode == '3') {
        // Show the current and next course progress steps
        // $information_title = get_lang('InfoAboutLastDoneAdvanceAndNextAdvanceNotDone');
        $last_done_advance =  $thematic->get_last_done_thematic_advance();
        $next_advance_not_done = $thematic->get_next_thematic_advance_not_done();
        $thematic_advance_info = $thematic->get_thematic_advance_list($last_done_advance);
        $thematic_advance_info2 = $thematic->get_thematic_advance_list($next_advance_not_done);
        $subTitle1 = get_lang('CurrentTopic');
        $subTitle2 = get_lang('NextTopic');
        $class1 = ' current';
    }

    if (!empty($thematic_advance_info)) {

        /*$thematic_advance = get_lang('CourseThematicAdvance').'&nbsp;'.
            $thematic->get_total_average_of_thematic_advances().'%';*/
        $thematic_advance = get_lang('CourseThematicAdvance');
        $thematicScore = $thematic->get_total_average_of_thematic_advances() . '%';
        $thematicUrl = api_get_path(WEB_CODE_PATH) .
            'course_progress/index.php?action=thematic_details&'.api_get_cidreq();
        $thematic_info = $thematic->get_thematic_list(
            $thematic_advance_info['thematic_id']
        );

        $thematic_advance_info['start_date'] = api_get_local_time(
            $thematic_advance_info['start_date']
        );
        $thematic_advance_info['start_date'] = api_format_date(
            $thematic_advance_info['start_date'],
            DATE_TIME_FORMAT_LONG
        );
        $userInfo = $_SESSION['_user'];
        $courseInfo = api_get_course_info();
        //die('<pre>'.print_r($courseInfo,1).'</pre>');
        $thematic_description_html = '
            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
            <div id="panel-thematic" class="panel panel-default">
                <div class="panel-heading">
                 <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                <h4>
                        '. $thematic_advance .' : '. $courseInfo['name'] . ' <b>( '. $thematicScore .' )</b>
                </h4>
                </a>
                </div>
                <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="thumbnail">
                                <img src="' . $userInfo['avatar'] . '" class="img-responsive">
                            </div>
                            <div class="progress">
                                        <div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" style="width: ' . $thematicScore . ';">
                                            '.$thematicScore.'
                                        </div>
                            </div>
                            <div class="separate"><a href="' . $thematicUrl . '" class="btn btn-block btn-info">' . get_lang('ShowFullCourseAdvance') . '</a></div>
                        </div>';

        $thematic_description_html .= '<div class="col-md-9">';

        $thematic_description_html .= '<div class="row">';
        $thematic_description_html .= '<div class="col-md-6 items-progress'.$class1.'">
                                    <div class="topics">' . $subTitle1 . '</div>
                                    <h4 class="title-topics">' . $thematic_info['title'] . '</h4>
                                    <p class="date">' . $thematic_advance_info['start_date'] . '</p>
                                    <div class="views">' . $thematic_advance_info['content'] . '</div>
                                    <p class="time">' . get_lang('DurationInHours') . ' : ' . $thematic_advance_info['duration'] . ' - <a href="' . $thematicUrl . '">' . get_lang('SeeDetail') . '</a></p>
                                </div>';

        if (!empty($thematic_advance_info2)) {
            $thematic_info2 = $thematic->get_thematic_list($thematic_advance_info2['thematic_id']);
            $thematic_advance_info2['start_date'] = api_get_local_time($thematic_advance_info2['start_date']);
            $thematic_advance_info2['start_date'] = api_format_date($thematic_advance_info2['start_date'], DATE_TIME_FORMAT_LONG);

            $thematic_description_html .= '<div class="col-md-6 items-progress">
                                                <div class="topics">'.$subTitle2.'</div>
                                                <h4 class="title-topics">'.$thematic_info2['title'].'</h4>
                                                <p class="date">'.$thematic_advance_info2['start_date'].'</p>
                                                <div class="views">'.$thematic_advance_info2['content'].'</div>
                                                <p class="time">'.get_lang('DurationInHours').' : '.$thematic_advance_info2['duration'].' - <a href="'.$thematicUrl.'">'.get_lang('SeeDetail').'</a></p>
                                            </div>';
        }
        $thematic_description_html.='</div>';

        $thematic_description_html.='</div></div></div></div></div></div>';



    }
}

$introduction_section .= '<div class="row"><div class="col-md-12">';
$introduction_section .=  $thematic_description_html;
$introduction_section .=  '</div>';

$introduction_section .=  '<div class="home-course-intro col-md-12"><div class="page-course">';

if ($intro_dispDefault) {
    if (!empty($intro_content)) {
        $introduction_section.='<div class="page-course-intro">';
        $introduction_section .=  $intro_content;
        $introduction_section.='</div>';
    }
}
$introduction_section .=  '</div></div>';

if ($intro_dispCommand) {
    if (empty($intro_content)) {
        // Displays "Add intro" commands
        $introduction_section .=  '<div id="courseintro_empty">';
        if (!empty ($GLOBALS['_cid'])) {
            $introduction_section .=  "<a href=\"".api_get_self()."?".api_get_cidreq()."&amp;intro_cmdAdd=1\">";
            $introduction_section .=  Display::return_icon('introduction_add.gif', get_lang('AddIntro')).' ';
            $introduction_section .=  "</a>";
        } else {
            $introduction_section .= "<a href=\"".api_get_self()."?intro_cmdAdd=1\">\n".get_lang('AddIntro')."</a>";
        }
        $introduction_section .= "</div>";

    } else {
        // Displays "edit intro && delete intro" commands
        $introduction_section .=  '<div id="courseintro_empty">';
        if (!empty ($GLOBALS['_cid'])) {
            $introduction_section .=
                "<a href=\"".api_get_self()."?".api_get_cidreq()."&amp;intro_cmdEdit=1\">".
                Display::return_icon('edit.png', get_lang('Modify'), '', ICON_SIZE_SMALL).
                "</a>";
            $introduction_section .=
                "<a href=\"".api_get_self()."?".api_get_cidreq()."&amp;intro_cmdDel=1\" onclick=\"javascript:
                if(!confirm('".addslashes(api_htmlentities(get_lang('ConfirmYourChoice'), ENT_QUOTES, $charset)).
                "')) return false;\">".
                Display::return_icon('delete.png', get_lang('Delete'), '', ICON_SIZE_SMALL).
                "</a>";
        } else {
            $introduction_section .=
                "<a href=\"".api_get_self()."?intro_cmdEdit=1\">".
                Display::return_icon('edit.png', get_lang('Modify'), '', ICON_SIZE_SMALL).
                "</a>";
            $introduction_section .=
                "<a href=\"".api_get_self()."?intro_cmdDel=1\" onclick=\"javascript:
                if(!confirm('".addslashes(api_htmlentities(get_lang('ConfirmYourChoice'), ENT_QUOTES, $charset)).
                "')) return false;\">".
                Display::return_icon('delete.png', get_lang('Delete'), '', ICON_SIZE_SMALL).
                "</a>";
        }
        $introduction_section .=  "</div>";
        // Fix for chrome XSS filter for videos in iframes - BT#7930
        $browser = api_get_navigator();
        if (strpos($introduction_section, '<iframe') !== false && $browser['name'] == 'Chrome') {
            header('X-XSS-Protection: 0');
        }
    }
}
$introduction_section .=  '</div>';

$browser = api_get_navigator();

if (strpos($introduction_section, '<iframe') !== false && $browser['name'] == 'Chrome') {
    header("X-XSS-Protection: 0");
}
