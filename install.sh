#!/bin/bash

# Specify the directory for Composer (root directory)
composer_directory="./"

# Specify the directory for Yarn (assets directory)
yarn_directory="./assets"

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Composer is not installed. Please install it and try again."
    exit 1
fi

# Check if Yarn is installed
# if ! command -v yarn &> /dev/null; then
#     echo "Yarn is not installed. Please install it and try again."
#     exit 1
# fi

# Run Composer in the root directory
echo "Running Composer in the root directory..."
cd "$composer_directory" || exit
composer install

# Run Yarn in the assets directory
# echo "Running Yarn in the assets directory..."
# cd "$yarn_directory" || exit
# yarn install

# Optional: Add additional commands as needed for your specific use case


echo "Installation completed successfully."
