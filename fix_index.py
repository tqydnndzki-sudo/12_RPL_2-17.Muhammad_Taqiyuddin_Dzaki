# Script to fix the index.php file by removing extra endif statements

input_file = r"c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index.php"
output_file = r"c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index_fixed.php"

# Lines to remove (the extra endif statements)
lines_to_remove = [1781, 1815]

with open(input_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Remove the extra endif statements (adjusting for 0-based indexing)
# We need to remove in reverse order to maintain line numbers
for line_num in sorted(lines_to_remove, reverse=True):
    if line_num <= len(lines):
        # Check if the line contains the extra endif
        if "<?php endif; ?>" in lines[line_num - 1]:
            print(f"Removing line {line_num}: {lines[line_num - 1].strip()}")
            lines.pop(line_num - 1)

# Write the fixed content to output file
with open(output_file, 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("Fixed file saved as index_fixed.php")