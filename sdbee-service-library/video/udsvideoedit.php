<?php

 /**
  * Video editing services
  *
  */
// Create overlay image on video input between 0 and 20 secs in lower right corner
ffmpeg -i input.mp4 -i image.png \
-filter_complex "[0:v][1:v] overlay=W-w:H-h:enable='between(t,0,20)'" \
-pix_fmt yuv420p -c:a copy \
output.mp4

overlay=25:25 = top left at 25 pixels  from borders

// SPlit on silnce taking into  account key video frames
#!/bin/bash

IN=$1
OUT=$2

true ${SD_PARAMS:="-55dB:d=0.3"};
true ${MIN_FRAGMENT_DURATION:="20"};
export MIN_FRAGMENT_DURATION

if [ -z "$OUT" ]; then
    echo "Usage: split_by_silence_kf input_media.mp4 output_template_%03d.mkv"
    echo "Depends on FFmpeg, Bash, Awk, Perl 5. Not tested on Mac or Windows."
    echo "This version of the script takes into account video keyframes by adjusting splitting points next keyframes"
    echo "There is a simpler version of the sript around"
    echo ""
    echo "Environment variables (with their current values):"
    echo "    SD_PARAMS=$SD_PARAMS       Parameters for FFmpeg's silencedetect filter: noise tolerance and minimal silence duration"
    echo "    MIN_FRAGMENT_DURATION=$MIN_FRAGMENT_DURATION    Minimal fragment duration"
    exit 1
fi

echo "Enumerating keyframes..." >& 2

KFS=$(
    ffprobe -v warning -select_streams v -show_packets -of  csv "$IN"  \
    | perl -wne '
        @a=(split /,/);
        print $a[4], " "
            if  $a[13] =~ /^K/ 
            and $a[2]==0;
    '
)

echo "Detected $(echo $KFS | wc -w) keyframes" >&2


echo "Determining split points..." >& 2

SPLITS=$(
    ffmpeg  -v warning -i "$IN" -af silencedetect="$SD_PARAMS",ametadata=mode=print:file=-:key=lavfi.silence_start -vn -sn  -f s16le  -y /dev/null \
    | grep lavfi.silence_start= \
    | cut -f 2-2 -d= \
    | perl -wne '
        our $prev;
        INIT { $prev = 0.0; }
        chomp;
        if (($_ - $prev) >= $ENV{MIN_FRAGMENT_DURATION}) {
            print "$_,";
            $prev = $_;
        }
    ' \
    | sed 's!,$!!'
)

echo "Splitting points are $SPLITS"

echo "Adjusting splitting points to keyframes..." >&2

export SPLITS
export KFS

SPLITS2=$(perl -we '
    our @kfs = split / /, $ENV{"KFS"};
    our @spl = split /,/, $ENV{"SPLITS"};
    my $k = 0;
    foreach $s (@spl) {
        last if $k > $#kfs;
        while (1) {
            last if $k > $#kfs;
            my $kf = $kfs[$k];
            $k += 1;
            if ($kf >= $s) {
                #print STDERR "$s->$kf\n";
                print "$kf,";
                last;
            }
        }
    }
' | sed 's!,$!!')

echo "Adjusted splitting points are $SPLITS2"

ffmpeg -v warning -i "$IN" -c copy -map 0 -reset_timestamps 1 -f segment -segment_times "$SPLITS2" "$OUT"


?>