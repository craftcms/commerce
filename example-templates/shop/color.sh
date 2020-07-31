#!/bin/sh

read -p "What is the current color? (gray|red|orange|yellow|green|teal|blue|indigo|purple|pink) " originalColor;
read -p "What is the new color? (gray|red|orange|yellow|green|teal|blue|indigo|purple|pink) " newColor;

replaceString="s/\-${originalColor}/\-${newColor}/g"

find -E ./  -regex ".*\.(html|twig)" -exec sed -i '' ${replaceString} {} \;