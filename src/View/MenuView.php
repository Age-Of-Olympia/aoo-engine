<?php

namespace App\View;

use Classes\Str;
use Classes\Db;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialSessionManager;

class MenuView
{
    public static function renderMenu(): void
    {
        if (!empty($_SESSION['playerId'])) {
            ob_start();

            // Menu buttons
            echo '<a href="index.php" id="show-damier" title="Vue"><button>&nbsp;<span class="ra ra-chessboard"></span>&nbsp;</button></a><a href="#" id="show-caracs" title="Caractéristiques"><button><span class="ra ra-muscle-up"></span>&nbsp;Caractéristiques</button></a><a href="inventory.php" id="show-inventory" title="Inventaire"><button><span class="ra ra-key"></span> Inventaire</button></a><!--a href="upgrades.php"><button><span class="ra ra-podium"></span> Améliorations</button></a--><a href="logs.php?light"><button><span class="ra ra-book"></span> Evènements</button></a><a href="map.php" title="Carte"><button>&nbsp;<span class="ra ra-scroll-unfurled"></span>&nbsp;</button></a><a href="forum.php?forum=Missives" title="Missives"><button id="missive-btn">&nbsp;<span class="ra ra-quill-ink"></span>&nbsp;</button></a><a href="account.php" title="Profil"><button>&nbsp;<span class="ra ra-wrench"></span>&nbsp;</button></a>';

            // Add tutorial button (feature-flagged)
            // Only show if enabled for player AND they haven't completed it yet
            if (TutorialFeatureFlag::isEnabledForPlayer($_SESSION['playerId'])) {
                $db = new Db();
                $sessionManager = new TutorialSessionManager($db);
                $hasCompleted = $sessionManager->hasCompletedBefore($_SESSION['playerId']);

                if (!$hasCompleted) {
                    echo '<a href="#" id="tutorial-start-btn" title="Tutoriel"><button style="background: #4CAF50;"><span class="ra ra-compass"></span>&nbsp;Tutoriel</button></a>';
                }
            }

            echo '<div id="load-caracs"></div>';

            // Output minified HTML
            echo Str::minify(ob_get_clean());

            // Menu event handlers (not minified)
            echo '<script>
                $(document).ready(function() {
                    $("#show-caracs").click(function(e) {
                        e.preventDefault();
                        if ($("#load-caracs").is(":hidden")) {
                            $.ajax({
                                type: "POST",
                                url: "load_caracs.php",
                                success: function(data) {
                                    $("#load-caracs").html(data).fadeIn();
                                }
                            });
                        } else {
                            $("#load-caracs").hide();
                        }
                    });

                    $(".menu-link").click(function(e) {
                        e.preventDefault();
                        let url = $(this).attr("href");
                        $.ajax({
                            type: "POST",
                            url: url,
                            success: function(data) {
                                $("#ajax-data").html(data);
                            }
                        });
                    });
                });
            </script>';

            // Tutorial button handler (feature-flagged, not minified)
            // Show if enabled for player (for replay option or new players)
            if (TutorialFeatureFlag::isEnabledForPlayer($_SESSION['playerId'])) {
                $db = new Db();
                $sessionManager = new TutorialSessionManager($db);
                $hasCompleted = $sessionManager->hasCompletedBefore($_SESSION['playerId']);

                // Only render JavaScript if button is visible OR if auto-start is triggered
                $shouldRenderJS = !$hasCompleted || (isset($_SESSION['auto_start_tutorial']) && $_SESSION['auto_start_tutorial']);

                if ($shouldRenderJS) {
                    echo '<script>
                    $(document).ready(function() {
                        console.log("[Menu] Setting up tutorial button handler");
                        console.log("[Menu] Button exists:", $("#tutorial-start-btn").length);

                        /* Tutorial start function (shared between button and auto-trigger) */
                        function startTutorialFlow() {
                            console.log("[Menu] Starting tutorial flow");

                            if (typeof window.initTutorial === "function" && !window.tutorialUI) {
                                console.log("[Menu] Initializing tutorial system");
                                window.initTutorial();
                            }

                            $.ajax({
                                url: "/api/tutorial/resume.php",
                                method: "GET",
                                dataType: "json",
                                success: function(response) {
                                    console.log("[Menu] Resume API response:", response);

                                    if (response.success && response.has_active_tutorial) {
                                        console.log("[Menu] Resuming active tutorial");
                                        if (typeof window.resumeTutorial === "function") {
                                            window.resumeTutorial();
                                        }
                                    } else {
                                        console.log("[Menu] Starting new tutorial");
                                        if (typeof window.startTutorial === "function") {
                                            window.startTutorial("first_time");
                                        }
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error("[Menu] Resume API error:", status, error, xhr.responseText);

                                    if (typeof window.startTutorial === "function") {
                                        window.startTutorial("first_time");
                                    } else {
                                        alert("Erreur: Le système de tutoriel n\'est pas chargé. Rechargez la page.");
                                    }
                                }
                            });
                        }

                        /* Button click handler (only if button exists) */
                        $("#tutorial-start-btn").click(function(e) {
                            e.preventDefault();
                            console.log("[Menu] Tutorial button clicked");
                            startTutorialFlow();
                        });
                        ' . (isset($_SESSION['auto_start_tutorial']) && $_SESSION['auto_start_tutorial'] ? '
                        /* Auto-trigger for new players */
                        console.log("[Menu] Auto-starting tutorial for new player");
                        setTimeout(function() {
                            startTutorialFlow();
                        }, 1000); /* Small delay to ensure page is fully loaded */
                        ' : '') . '
                    });
                </script>';
                }
            }
        }
    }
}
