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

###############################################################################
/*
 * This PHP script is responsible for downloading segment(s)
 * pointed to by the MPD.
 * @name: SegmentDownload.php
 * @entities:
 *      @functions{
 *          download_data($directory, $array_file),
 *          remote_file_size($url),
 *          partial_download($url, $begin, $end, &$ch)
 *      }
 */
###############################################################################

/*
 * Download segments
 * @name: download_data
 * @input: $directory - download directory for the segment(s)
 *         $array_file - URL of the segment(s) to be downloaded
 *         $is_dolby - Boolean: is the media a Dolby codec?
 * @output: $file_sizearr - array of original size(s) of the segment(s)
 */
function download_data($directory, $array_file, $is_subtitle_rep, $is_dolby)
{
    global $session, $mpdHandler,
           $hls_byte_range_begin, $hls_byte_range_size, $hls_manifest, $availability_times, $modules;


    echo("Requested URLs: ". var_export($array_file) . "\n");

    //create text file containing the original size of Mdat box that is ignored
    $sizefile = fopen($session->getSelectedRepresentationDir() . '/mdatoffset', 'a+b');

    $segment_count = sizeof($array_file);
    $initoffset = 0; // Set pointer to 0
    $mdat_index = 0;
    // Это по идее не нужно.
    // $totaldownloaded = 0; // bytes downloaded
    // Эти две переменные по ходу не нужны.
    // $totalDataDownloaded = 0;
    // $totalDataProcessed = 0; // bytes processed within segments
    $downloadMdat = 0;
    $downloadAll = $is_dolby;
    // Этот массив по ходу не нужен.
    // $byte_range_array = array();
    $ch = curl_init();

    foreach ($modules as $module) {
        if ($module->name == "DASH-IF Low Latency") {
            if ($module->isEnabled()) {
                $count = sizeof($availability_times[$mpdHandler->getSelectedAdaptationSet()]
                                                   [$mpdHandler->getSelectedRepresentation()]['ASAST']);
                $media_segment_index = ($count == sizeof($array_file)) ? 0 : 1;
                $start_index = 0;
            }
            break;
        }
    }

    echo("\t\t\t\t\t\tThis representation has " . $segment_count . " segments\n");
    # Iterate over $array_file
    for ($index = 0; $index < $segment_count; $index++) {
        
        echo("\t\t\t\t\t\t\tChecking segment " . $index . "\n");

        
        $filePath = $array_file[$index];

        echo("\t\t\t\t\t\t\t\tfilePath: " . $filePath . "\n");
        $filename = basename($filePath);
        echo("\t\t\t\t\t\t\t\tfilename: " . $filename . "\n");


        $file_information = remote_file_size($filePath);
        $file_exists = $file_information[0];
        $file_size = ($hls_manifest && $hls_byte_range_size) ?
          $hls_byte_range_size[$index] + $hls_byte_range_begin[$index] : $file_information[1];

        echo("\t\t\t\t\t\t\t\tfile_exists: " . $file_exists . "\n");
        echo("\t\t\t\t\t\t\t\tfile_size:   " . $file_size . " bytes\n");

        if (!$file_exists) {
            $missing = (!$hls_manifest) ?
              fopen($session->getPeriodDir($mpdHandler->getSelectedPeriod()) . '/missinglink.txt', 'a+b') :
                    fopen($session->getDir() . '/missinglink.txt', 'a+b');
            fwrite($missing, $filePath . "\n");
            continue;
        }

        // if ($file_size == 0) {
        // Временно поставил это условие как основное, чтобы качался сразу весь файл.
        if (true) {
            //download all
            $content = partial_download($filePath, $ch);
            if (!$content) {
                // add to missing link
                continue;
            }

            $sizepos = $sizepos = ($hls_byte_range_begin) ? $hls_byte_range_begin[$index] : 0;
            $location = 1;
            $box_name = null;
            $box_size = 0;
            $newfile = fopen($directory . "/" . $filename, 'a+b');

            # Assure that the pointer doesn't exceed size of downloaded bytes
            $byte_array = unpack('C*', $content);
            while ($location < sizeof($byte_array)) {
                $diff = sizeof($byte_array) - $location;
                if ($diff < 3) {
                    break;
                }

                $box_size = $byte_array[$location] * 16777216 +
                            $byte_array[$location + 1] * 65536 +
                            $byte_array[$location + 2] * 256 +
                            $byte_array[$location + 3];
                $box_name = substr($content, $location + 3, 4);
                echo("\t\t\t\t\t\t\t\tBox name: " . $box_name . "\n");
                echo("\t\t\t\t\t\t\t\tBox size: " . $box_size . "\n");
                if ($downloadAll || $box_name != 'mdat') {
                    // Пока тип бокса - не mdat, складываем боксы в наш файл.
                    fwrite($newfile, substr($content, $location - 1, $box_size));
                } else {
                    # If it is mdat box
                    # If mdat downloading is chosen
                    #   Stuff complete mdat data with zeros
                    # Else
                    #   Add the original size of the mdat to text file without the name and size bytes(8 bytes)
                    #   Copy only the mdat name and size to the segment

                    // Здесь идёт проверка значения переменной $downloadMdat.
                    // Эта переменная всегда 0.
                    if ($downloadMdat) {
                        fwrite($sizefile, ($initoffset + $sizepos + 8) . " " . 0 . "\n");
                        fwrite($newfile, substr($content, $location - 1, 8));
                        fwrite($newfile, str_pad("0", $box_size - 8, "0"));
                    } else {
                        fwrite($sizefile, ($initoffset + $sizepos + 8) . " " . ($box_size - 8) . "\n");
                        fwrite($newfile, substr($content, $location - 1, 8));

                        ## For DVB subtitle checks related to mdat content
                        ## Save the mdat boxes' content into xml files
                        if ($is_subtitle_rep) {
                            $subtitle_xml_string = '<subtitle>';
                            $mdat_file = $directory . '/Subtitles/' . $mdat_index . '.xml';
                            fopen($mdat_file, 'w');
                            chmod($mdat_file, 0777);
                            $mdat_index++;

                            $text = substr($content, ($initoffset + $location + 7), ($box_size - 7));
                            $text = substr($text, strpos($text, '<tt'));
                            $subtitle_xml_string .= $text;

                            $subtitle_xml_string = substr(
                                $subtitle_xml_string,
                                0,
                                strrpos($subtitle_xml_string, '>') + 1
                            );
                            $subtitle_xml_string .= '</subtitle>';
                            $mdat_data = simplexml_load_string($subtitle_xml_string);
                            $mdat_data->asXML($mdat_file);
                        }
                    }
                }

                $sizepos = $sizepos + $box_size;
                $location = $location + $box_size;
                $file_size = $file_size + $box_size;

                // По идее это не нужно.
                // $totalDataDownloaded = $totalDataDownloaded + $box_size;
                // $percent = (int) (100 * $index / (sizeof($array_file) - 1));
            }

            # Modify node and sav it to a progress report
        } else {
            $sizepos = $sizepos = ($hls_byte_range_begin) ? $hls_byte_range_begin[$index] : 0;
            $remaining = $file_size - $sizepos;

            echo("\t\t\t\t\t\t\t\tsizepos:     " . $sizepos . "\n");
            echo("\t\t\t\t\t\t\t\tremaining:   " . $remaining . "\n");

            while ($sizepos < $file_size) {
                // Равен 1 потому, что у нас возвращается ассоциативный массив (его индексы начинаются с 1).
                $location = 1; // temporary pointer
                $name = null; // box name
                $box_size = 0; // box size

                // create an empty mp4 file to contain data needed from remote segment
                // Создаем новый файл, в который будем сохранять скачанное содержимое.
                $newfile = fopen($directory . "/" . $filename, 'a+b');

                # Download the partial content and unpack
                // Здесь мы запрашиваем загрузку файла от 0-го байта до 1500-го.
                // Сервер легко может проигнорировать это требование и отдать файл целиком (что обычно и происходит).
                // А ещё момент, что границы скачивания входят в интервал, т.е. если запросить 0-1500, то будет файл размером 1501 байт.
        
                // То есть можно сказать, что эта функция скачивает либо весь файл целиком (если сервер не поддерживает RANGE),
                // либо 1501 байт файла.
                // Пример, где сервер НЕ поддерживает RANGE: "http://192.168.189.30/mdrm/2023_57_sekund_ottcontentasset/hd_1b23cda4_v1/manifest.mpd?debug=3"
                // Пример, где сервер    поддерживает RANGE: "https://bitmovin-a.akamaihd.net/content/MI201109210084_1/mpds/f08e80da-bf1d-4e3d-8899-f0f6155f6efa.mpd"
                echo("\t\t\t\t\t\t\t\tDownloading segment...\n");
                $content = partial_download($filePath, $ch, $sizepos, $sizepos + 1500);

                echo("\t\t\t\t\t\t\t\tLength of binary string: " . strlen($content) . "\n");

                // Вот тут и зарыта самая главная собака. Эта функция возвращает АССОЦИАТИВНЫЙ массив.
                // Его индексы начинаются с 1-цы. Поэтому $location по умолчанию равно 1.
                $byte_array = unpack('C*', $content);
                // А вот это по ходу вообще не нужно.
                // $byte_range_array = array_merge($byte_range_array, $byte_array);
                
                echo("\t\t\t\t\t\t\t\tdownloaded size:   " . sizeof($byte_array) . "\n");

                # Update the total size of downloaded data
                // Вот здесь неправильно. Должно быть +1501.
                // И похоже, что оно вообще не нужно.
                // $totalDataDownloaded = $totalDataDownloaded + 1500;

                # Assure that the pointer doesn't exceed size of downloaded bytes
                while ($location < sizeof($byte_array)) {
                    // location по умолчанию равен 1. Потому что это ассоциативный массив.
                    echo("\t\t\t\t\t\t\t\tlocation:     " . $location . "\n");
                    $diff = sizeof($byte_array) - $location;
                    echo("\t\t\t\t\t\t\t\tdiff:     " . $diff . "\n");
                    if ($diff < 3) {
                        //$prev_data = array_slice($byte_array, $location, $diff);
                        break;
                    } else {
                        // Насколько я понял, здесь парсится первый бокс контейнера MP4. 
                        echo("\t\t\t\t\t\t\t\tbyte_array[" . $location . "]: " . $byte_array[$location] . "\n");
                        echo("\t\t\t\t\t\t\t\tbyte_array[" . ($location + 1) . "]: " . $byte_array[$location + 1] . "\n");
                        echo("\t\t\t\t\t\t\t\tbyte_array[" . ($location + 2) . "]: " . $byte_array[$location + 2] . "\n");
                        echo("\t\t\t\t\t\t\t\tbyte_array[" . ($location + 3) . "]: " . $byte_array[$location + 3] . "\n");
                        $box_size = $byte_array[$location] * 16777216 +
                                  $byte_array[$location + 1] * 65536 +
                                  $byte_array[$location + 2] * 256 +
                                  $byte_array[$location + 3];
                        echo("\t\t\t\t\t\t\t\tcalculated box_size:     " . $box_size . "\n");
                    }

                    $size_copy = $box_size; // keep a copy of size to add to $location when it is replaced by remaining
                    if ($box_size > $remaining) {
                        $size_copy = $remaining;
                    }

                    // Вот этот блок по идее нигде не нужен.
                    /*
                    if ($segment_count === 1) { // if presentation contain only single segment
                        // total data being processed
                        $totaldownloaded += (!$hls_byte_range_begin) ? $box_size : $size_copy;
                        $percent = (int) (100 * $totaldownloaded / $file_size); //get percent over the whole file size
                    } else {
                        $percent = (int) (100 * $index / (sizeof($array_file) - 1)); // percent of remaining segments
                    }
                    */

                    //get box name exist in the next 4 bytes from the bytes containing the size
                    $name = substr($content, $location + 3, 4);
                    if ($downloadAll || $name != 'mdat') {
                        # If it is not mdat box download it
                        # The total size being downloaded is location + size
                        # If the amount of byte processed < the data downloaded at begining
                        #   Copy the whole data to the new mp4 file
                        # Else
                        #   Download the rest of the box from the remote segment
                        #   Copy the rest to the file
                        $total = $location + ((!$hls_byte_range_begin) ? $box_size : $size_copy);
                        if ($total < sizeof($byte_array)) {
                            fwrite($newfile, substr(
                                $content,
                                $location - 1,
                                ((!$hls_byte_range_begin) ? $box_size : $size_copy)
                            ));
                        } else {
                            $rest = partial_download(
                                $filePath,
                                $ch,
                                $sizepos,
                                $sizepos + ((!$hls_byte_range_begin) ? $box_size : $size_copy) - 1
                            );
                            // По идее это не нужно.
                            // $totalDataDownloaded += ((!$hls_byte_range_begin) ? $box_size : $size_copy) - 1;
                            fwrite($newfile, $rest);
                        }
                    } else {
                        # If it is mdat box
                        # If mdat downloading is chosen
                        #   Stuff complete mdat data with zeros
                        # Else
                        #   Add the original size of the mdat to text file without the name and size bytes(8 bytes)
                        #   Copy only the mdat name and size to the segment
                        if ($downloadMdat) {
                            fwrite($sizefile, ($initoffset + $sizepos + 8) . " " . 0 . "\n");
                            fwrite($newfile, substr($content, $location - 1, 8));
                            fwrite($newfile, str_pad(
                                "0",
                                ((!$hls_byte_range_begin) ? $box_size : $size_copy) - 8,
                                "0"
                            ));
                        } else {
                            fwrite(
                                $sizefile,
                                ($initoffset + $sizepos + 8) . " " .
                                (((!$hls_byte_range_begin) ? $box_size : $size_copy) - 8) . "\n"
                            );
                            fwrite($newfile, substr($content, $location - 1, 8));

                            ## For DVB subtitle checks related to mdat content
                            ## Save the mdat boxes' content into xml files
                            if ($is_subtitle_rep) {
                                $subtitle_xml_string = '<subtitle>';
                                $mdat_file = $directory . '/Subtitles/' . $mdat_index . '.xml';
                                fopen($mdat_file, 'w');
                                chmod($mdat_file, 0777);
                                $mdat_index++;
                                $total = $location + $box_size;
                                if ($total < sizeof($byte_array)) {
                                    $text = substr($content, ($initoffset + $location + 7), ($box_size - 7));
                                    $text = substr($text, strpos($text, '<tt'));
                                    $subtitle_xml_string .= $text;
                                } else {
                                    $rest = partial_download(
                                        $filePath,
                                        $ch,
                                        $sizepos + 8,
                                        $sizepos + $box_size - 1
                                    );
                                    $text = $rest;
                                    $text = substr($text, strpos($text, '<tt'));
                                    $subtitle_xml_string .= $text;
                                    //fwrite($mdat_file, $rest);
                                }
                                $subtitle_xml_string = substr(
                                    $subtitle_xml_string,
                                    0,
                                    strrpos($subtitle_xml_string, '>') + 1
                                );
                                $subtitle_xml_string .= '</subtitle>';
                                $mdat_data = simplexml_load_string($subtitle_xml_string);
                                $mdat_data->asXML($mdat_file);
                            }
                        }
                    }

                    $sizepos = $sizepos + ((!$hls_byte_range_begin) ? $box_size : $size_copy); // move size pointer
                    $remaining = $file_size - $sizepos;
                    $location = $location + $box_size; // move location pointer

                    if ($remaining == 0) {
                        break;
                    }
                }

                # Modify node and sav it to a progress report
            }
        
        }
        

        $initoffset = (!$hls_byte_range_begin) ? $initoffset + $file_size : 0;
        // По идее это не нужно.
        // $totalDataProcessed = $totalDataProcessed + $totalDataDownloaded;
        $sizearray[] = $file_size;

        fflush($newfile);
        fclose($newfile);
    }

    # All done
    curl_close($ch);
    fflush($sizefile);
    fclose($sizefile);
//    fflush($missing);
//    fclose($missing);

    if (!isset($sizearray)) {
        $sizearray = 0;
    }

    return $sizearray;
}

/*
 * Get the size of the segment remotely without downloading it
 * @name: remote_file_size
 * @input: $url - URL of the segment of which the size is requested
 * @output: FALSE or segment size
 */
function remote_file_size($url)
{
    $file_exists = false;
    $file_size = 0;

    # Get all header information
    $data = get_headers($url, true);
    if (
        $data[0] === 'HTTP/1.1 404 Not Found' ||
        $data[0] === 'HTTP/1.0 404 Not Found' ||
        $data[0] === 'HTTP/2 404 Not Found'
    ) {
        return [$file_exists, $file_size];
    }

    $file_exists = true;
    # Look up validity
    if (isset($data['Content-Length'])) {
        $file_size = (int) $data['Content-Length'];
    }

    return [$file_exists, $file_size];
}

/*
 * Download partial bytes of a file by giving file location, start and end byte
 * @name: partial_download
 * @input: $url - URL of the segment of which the size is requested
 *         $begin - byte to start from
 *         $end - byte to end at
 *         $ch - curl object
 * @output: downloaded content
 */
function partial_download($url, &$ch, $begin = 0, $end = 0)
{
    global $session;

    # Temporary container for partial segments downloaded
    $temp_file = $session->getDir() . '//' . "getthefile.mp4";
    // echo("Temp file: " . $temp_file . "\n");
    if (!($fp = fopen($temp_file, "w+"))) {
        exit;
    }

    // echo("Begin: " . $begin . "\n");
    // echo("End:   " . $end . "\n");

    # Add curl options and execute
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_FAILONERROR => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 500,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 0,
        CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
        CURLOPT_FILE => $fp
    );
    curl_setopt_array($ch, $options);

    curl_setopt($ch, CURLOPT_NOPROGRESS, false);

    // https://curl.se/libcurl/c/CURLOPT_RANGE.html
    // https://stackoverflow.com/questions/34458248/libcurl-easy-handle-curlopt-range-option-is-not-working
    // The range option may be ignored by server. So we should not rely on it.
    if ($end != 0) {
        $range = $begin . '-' . $end;
        // echo("Range:   " . $range . "\n");
        curl_setopt($ch, CURLOPT_RANGE, $range);
    }

    curl_exec($ch);

    # Check the downloaded content
    fclose($fp);
    $content = file_get_contents($temp_file);


    return $content;
}
