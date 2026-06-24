#!/bin/bash

function show_help() {
    echo "Usage: ./start.sh [option]"
    echo ""
    echo "Options:"
    echo "  <no option>    Start with local data"
    echo "  game-data      Start with the aoo-game-data repository"
    echo "  help           Show this help message"
    echo ""
}

if [ "$1" == "help" ]; then
    show_help
    exit 0
fi

if [ "$1" == "game-data" ]; then
    # Get aoo-game-data branch
    GAME_DATA_BRANCH=$(cd ../aoo-game-data && git branch --show-current)
    echo "Using aoo-game-data (branch: $GAME_DATA_BRANCH)"

    export DB_NAME="aoo4"
    export DB_CONFIG="./config/db_constants.php"
    export AOO_GAME_DATA="../aoo-game-data/datas"
    export AOO_GAME_IMG="../aoo-game-data/img"
    docker-compose up
else
    echo "Starting with local data..."
    export DB_NAME="aoo4"
    export DB_CONFIG="./config/db_constants.php"
    export AOO_GAME_IMG="./img"
    docker-compose up
fi
