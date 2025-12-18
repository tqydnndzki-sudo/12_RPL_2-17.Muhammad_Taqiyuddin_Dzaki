# Script to remove specific lines from a file

input_file = r"c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index_fixed.php"
output_file = r"c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\index_final.php"

# Lines to remove (1-based indexing)
lines_to_remove = [1781, 1815]

with open(input_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Remove the lines (convert to 0-based indexing and remove in reverse order)
for line_num in sorted(lines_to_remove, reverse=True):
    if 1 <= line_num <= len(lines):
        print(f"Removing line {line_num}: {repr(lines[line_num-1])}")
        lines.pop(line_num-1)

# Write the result to output file
with open(output_file, 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("File processed successfully!")