#!/bin/bash

function show_help() {
    echo "Usage: ./start.sh [option] [force]"
    echo ""
    echo "Options:"
    echo "  <no option>    Start with local data"
    echo "  game-data     Start with aoo-game-data repository"
    echo "                When on saison-2 branch, automatically restores"
    echo "                the latest dump from db_dumps/saison-2-*.sql"
    echo "  help          Show this help message"
    echo ""
    echo "Additional arguments:"
    echo "  force         Force restore of database dump even if it exists"
    echo ""
}

function restore_dump() {
    local dump_file=$1
    local db_name=$2
    local is_saison2=$3
    local force_restore=$4
    echo "Restoring database from dump..."
    
    # Check if container is running and mysql is responding
    if ! docker exec aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot -e "SELECT 1;" >/dev/null 2>&1; then
        echo "Error: Database container is not responding"
        docker-compose down
        exit 1
    fi
    
    # Check if database exists
    if docker exec aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot -e "USE $db_name" 2>/dev/null; then
        if [ "$is_saison2" = "true" ] && [ "$force_restore" != "true" ]; then
            echo "Database $db_name already exists, skipping restore for saison-2"
            return 0
        else
            # Drop and recreate for force restore or non-saison2
            echo "Dropping existing database..."
            docker exec -i aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot -e "DROP DATABASE $db_name;"
        fi
    fi
    
    # Create database if it doesn't exist
    echo "Creating database $db_name..."
    if ! docker exec -i aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot -e "CREATE DATABASE IF NOT EXISTS $db_name;"; then
        echo "Error: Failed to create database"
        docker-compose down
        exit 1
    fi
    
    # Restore dump
    if ! docker exec -i aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot $db_name < "$dump_file"; then
        echo "Error: Failed to restore database from dump"
        docker-compose down
        exit 1
    fi
    
    # Verify database exists and has tables
    if ! docker exec aoo-engine-mariadb-aoo4-1 mariadb -uroot -ppasswordRoot -e "USE $db_name; SHOW TABLES;" | grep -q .; then
        echo "Error: Database restore verification failed - no tables found"
        docker-compose down
        exit 1
    fi
    
    echo "Database restored successfully!"
}

if [ "$1" == "help" ]; then
    show_help
    exit 0
fi

if [ "$1" == "game-data" ]; then
    # Get aoo-game-data branch
    GAME_DATA_BRANCH=$(cd ../aoo-game-data && git branch --show-current)
    echo "Using aoo-game-data (branch: $GAME_DATA_BRANCH)"
    
    if [ "$GAME_DATA_BRANCH" == "saison-2" ]; then
        echo "Detected saison-2 branch, will restore from dump..."
        # Find latest saison-2 dump
        LATEST_DUMP=$(ls -t ../aoo-game-data/db_dumps/saison-2-*.sql 2>/dev/null | head -n1)
        if [ -n "$LATEST_DUMP" ]; then
            echo "Found latest dump file: $(basename $LATEST_DUMP)"
            export DB_NAME="aoo4_saison2"
            export DB_CONFIG="./config/db_constants_s2.php"
            export AOO_GAME_DATA="../aoo-game-data/datas"
            export AOO_GAME_IMG="../aoo-game-data/img"
            docker-compose up -d
            # Wait for database to be ready
            echo "Waiting for database to be ready..."
            sleep 10
            restore_dump "$LATEST_DUMP" "$DB_NAME" "true" "${2:-false}"
            docker-compose logs -f
        else
            echo "Error: no saison-2 dump files found in ../aoo-game-data/db_dumps/"
            exit 1
        fi
    else
        export DB_NAME="aoo4"
        export DB_CONFIG="./config/db_constants.php"
        export AOO_GAME_DATA="../aoo-game-data/datas"
        export AOO_GAME_IMG="../aoo-game-data/img"
        docker-compose up
    fi
else
    echo "Starting with local data..."
    export DB_NAME="aoo4"
    export DB_CONFIG="./config/db_constants.php"
    export AOO_GAME_IMG="./img"
    docker-compose up
fi
