<?php

/* This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function process_MPD($parseSegments = false)
{
    global $mpd_dom, $mpd_features, $mpd_validation_only, $current_period, $profiles;

    global $session;

    global $modules;

    global $logger;

    global $mpd_url;

    global $mpdHandler;


    $logger->parseSegments = $parseSegments;

    $mpd_dom = mpd_load();
    if (!$mpd_dom) {
        ///\RefactorTodo Add global error message!
        fwrite(STDERR, "Unable to load mpd dom\n");
        //die("Error: Failed loading XML file\n");
        return;
    }



    foreach ($modules as $module) {
        if ($module->isEnabled()) {
            $module->hookBeforeMPD();
        }
    }


    $mpdHandler = new DASHIF\MPDHandler($mpd_url);

    ## Get MPD features into an array
    ///\RefactorTodo Remove this global!!
    $mpd_features = $mpdHandler->getFeatures();


    ///\RefactorTodo Remove this global!!
    $profiles = $mpdHandler->getProfiles();



    //------------------------------------------------------------------------//
    ## Perform MPD Validation
    ## Write to MPD report
    ## If only MPD validation is requested or inferred, stop
    ## If any error is found in the MPD validation process, stop
    ## If no error is found, then proceed with segment validation below
//    $valid_mpd = validate_MPD();



    foreach ($modules as $module) {
        if ($module->isEnabled()) {
            $module->hookMPD();
        }
    }

    if (!$parseSegments) {
        fwrite(STDERR, ($parseSegments ? "DO " : "DO NOT ") . "parse segments\n");
        return;
    }

    //------------------------------------------------------------------------//
    ## Perform Segment Validation for each representation in each adaptation set within the current period
    if ($mpdHandler->getDom()->getElementsByTagName('SegmentList')->length !== 0) {
      return;
    }
    if ($mpd_features['type'] !== 'dynamic') {
        $mpdHandler->selectPeriod(0);
        $current_period = 0;
    }
    while ($mpdHandler->getSelectedPeriod() < sizeof($mpdHandler->getFeatures()['Period'])) {
        processAdaptationSetOfCurrentPeriod();

        if ($logger->getModuleVerdict("HEALTH") == "FAIL") {
            return;
        }

        if ($mpdHandler->getFeatures()['type'] === 'dynamic') {
            break;
        }

        $current_period++;
        $mpdHandler->selectNextPeriod();
    }
    if ($mpdHandler->getSelectedPeriod() >= 1) {
        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $module->hookPeriod();
            }
        }
    }
}

function processAdaptationSetOfCurrentPeriod()
{
    global  $current_adaptation_set, $adaptation_set_template,$current_representation,$reprsentation_template,
            $additional_flags, $current_period;

    global $session, $logger;

    global $modules, $mpdHandler;

    $period = $mpdHandler->getCurrentPeriodFeatures();
    $segment_urls = $mpdHandler->getSegmentUrls();

    global $logger;

    $adaptation_sets = $period['AdaptationSet'];
    while ($current_adaptation_set < sizeof($adaptation_sets)) {
        if ($logger->getModuleVerdict("HEALTH") == "FAIL") {
            break;
        }
        $adaptation_set = $adaptation_sets[$current_adaptation_set];
        $representations = $adaptation_set['Representation'];

        $adaptationDirectory = $session->getAdaptationDir($current_period, $current_adaptation_set);


        while ($current_representation < sizeof($representations)) {
            if ($logger->getModuleVerdict("HEALTH") == "FAIL") {
                break;
            }
            $representation = $representations[$current_representation];
            $segment_url = $segment_urls[$current_adaptation_set][$current_representation];

            $representationDirectory = $session->getRepresentationDir($current_period, $current_adaptation_set, $current_representation);


            $additional_flags = '';
            foreach ($modules as $module) {
                if ($module->isEnabled()) {
                    $module->hookBeforeRepresentation();
                }
            }

            $logger->setModule("HEALTH");
            validate_segment($adaptationDirectory, $representationDirectory, $period, $adaptation_set, $representation, $segment_url, $is_subtitle_rep);
            $logger->write();
            if ($logger->getModuleVerdict("HEALTH") == "FAIL") {
                break;
            }

            foreach ($modules as $module) {
                if ($module->isEnabled()) {
                    $module->hookRepresentation();
                }
            }

            $current_representation++;
        }
        if ($logger->getModuleVerdict("HEALTH") == "FAIL") {
            break;
        }

        ## Representations in current Adaptation Set finished
        crossRepresentationProcess();

        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $module->hookBeforeAdaptationSet();
            }
        }

        $current_representation = 0;
        $current_adaptation_set++;
    }

    if ($logger->getModuleVerdict("HEALTH") != "FAIL") {
    ## Adaptation Sets in current Period finished
        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $module->hookAdaptationSet();
            }
        }
    }
    //err_file_op(2);
    $current_adaptation_set = 0;
}
