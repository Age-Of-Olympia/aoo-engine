<?php

if(!empty($_SESSION['playerId'])){


    $svgUrl = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';


    if(!file_exists($svgUrl)){

        // coords
        $db = new Db();

        $player = new Player($_SESSION['playerId']);

        $coords = $player->get_coords();


        $caracsJson = json()->decode('players', $player->id .'.caracs');

        if(!$caracsJson){

            $player->get_caracs();

            $p = $player->caracs->p;
        }
        else{

            $p = $caracsJson->p;
        }


        $view = new View($coords, $p);

        $data = $view->get_view();

        $myfile = fopen($svgUrl, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);

        echo $data;

        echo '<sup>La vue a été rafraîchie!</sup>';
    }

    else{

        echo file_get_contents($svgUrl);
    }

    echo '<div id="ajax-data"></div>';


    ?>
    <script>
    $(document).ready(function(){


        window.clickedCases = [];


        $('.case').click(function(e){


            var coords = $(this).data('coords');

            var i = $(this).attr('x');
            var j = $(this).attr('y');


            var $case = $('[x="'+ i +'"][y="'+ j +'"]');

            if($case.not('.case, [data-table="tiles"], [data-table="foregrounds"]')[0]){


                if(window.clickedCases[coords]){


                    let data = window.clickedCases[coords];

                    $('#ajax-data').html(data);

                    return false;
                }


                $.ajax({
                    type: "POST",
                    url: 'observe.php',
                    data: {'coords':coords}, // serializes the form's elements.
                    success: function(data)
                    {
                        // alert(data);

                        console.log('db query');

                        $('#ajax-data').html(data);

                        window.clickedCases[coords] = data;
                    }
                });

                return false;
            }


            let [x, y] = coords.split(',');


            // show coords button
            $('#ajax-data').html('<div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button></div>');


            if($case.hasClass('go')){


                $('#go-rect')
                    .show()
                    .attr({'x': i, 'y': j})
                    .data('coords', x +','+ y);

                var imgY = j - 20 ;

                $('#go-img').show().attr({'x': i, 'y': imgY});
            }
        });


        $('#go-rect').click(function(e){

            var coords = $(this).data('coords');

            $('#go-rect').off('click');
            $('#view').css({'filter':'grayscale(1)', 'transition':'filter 0.5s'});

            $.ajax({
                type: "POST",
                url: 'go.php',
                data: {'coords':coords}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);

                    if(data.trim() != ''){


                        $('#ajax-data').html(data);

                        return false;
                    }

                    document.location.reload();
                }
            });
        });
    });
    </script>
    <?php
}
