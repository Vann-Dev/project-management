#!/bin/bash
# Screenshot each test function from multiple test files

# Array of test files to process
TEST_FILES=(
    "tests/Feature/Security/OwaspApiSecurityTest.php"
    "tests/Feature/Security/OwaspAuthenticationTest.php"
    "tests/Feature/Security/OwaspAuthorizationTest.php"
    "tests/Feature/Security/OwaspCryptographyTest.php"
    "tests/Feature/Security/OwaspInjectionTest.php"
    "tests/Feature/Security/OwaspSessionTest.php"
)

OUTPUT_DIR="test_screenshots"

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Process each test file
for INPUT_FILE in "${TEST_FILES[@]}"; do
    if [ ! -f "$INPUT_FILE" ]; then
        echo "⚠ Skipping $INPUT_FILE (file not found)"
        continue
    fi

    # Extract class name for subdirectory
    CLASS_NAME=$(basename "$INPUT_FILE" .php)
    CLASS_OUTPUT_DIR="$OUTPUT_DIR/$CLASS_NAME"
    mkdir -p "$CLASS_OUTPUT_DIR"

    echo ""
    echo "📸 Processing: $CLASS_NAME"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Read the file and extract each public function
awk '
BEGIN { in_function = 0; brace_count = 0; }

/public function test_/ {
    in_function = 1
    brace_count = 0

    # Extract function name
    match($0, /public function (test_[a-zA-Z_]+)/, arr)
    func_name = arr[1]

    # Start capturing
    buffer = $0 "\n"
    next
}

in_function {
    buffer = buffer $0 "\n"

    # Count braces
    for (i = 1; i <= length($0); i++) {
        char = substr($0, i, 1)
        if (char == "{") brace_count++
        if (char == "}") brace_count--
    }

    # When braces close, we have the complete function
    if (brace_count == 0 && match(buffer, /\{/)) {
        # Save to temp file
        temp_file = "temp_" func_name ".php"
        print "<?php" > temp_file
        print buffer >> temp_file
        close(temp_file)

        # Generate PNG with silicon
        output_png = "'"$CLASS_OUTPUT_DIR"'/" func_name ".png"
        cmd = "silicon " temp_file " -o " output_png " --theme Dracula --font \"DejaVu Sans Mono\" --shadow-blur-radius 15 --line-offset 48"
        system(cmd)

        # Remove temp file
        system("rm " temp_file)

        print "✓ Created: " output_png

        # Reset
        in_function = 0
        buffer = ""
        func_name = ""
    }
}
' "$INPUT_FILE"

    # Show count for this class
    CLASS_COUNT=$(ls -1 "$CLASS_OUTPUT_DIR"/*.png 2>/dev/null | wc -l)
    echo "✓ $CLASS_COUNT screenshots created for $CLASS_NAME"
done

# Final summary
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ All done! Screenshots saved to $OUTPUT_DIR/"
TOTAL_COUNT=$(find "$OUTPUT_DIR" -name "*.png" 2>/dev/null | wc -l)
echo "📊 Total: $TOTAL_COUNT files across all test classes"
