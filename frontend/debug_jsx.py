import sys
import re

def find_mismatched_tags(content):
    # Regex to find JSX tags (opening and closing)
    # This is a simplified regex and won't handle all edge cases (like comments or string literals),
    # but it's a good start for detecting imbalances in structured JSX.
    tag_pattern = re.compile(r'<(/?)([a-zA-Z0-9\.]+)(\s+[^>]*?)?(/?)(?<!=)>')
    
    stack = []
    lines = content.split('\n')
    
    for i, line in enumerate(lines):
        # Skip comments
        if '{/*' in line and '*/}' in line:
            line = re.sub(r'\{/\*.*?\*/\}', '', line)
        
        for match in tag_pattern.finditer(line):
            is_closing = match.group(1) == '/'
            tag_name = match.group(2)
            is_self_closing = match.group(4) == '/'
            
            # Skip common self-closing HTML tags if not marked as such
            if tag_name.lower() in ['img', 'br', 'hr', 'input', 'meta', 'link']:
                continue
                
            if is_self_closing:
                continue
                
            if is_closing:
                if not stack:
                    print(f"Error: Unexpected closing tag </{tag_name}> at line {i+1}")
                else:
                    top_tag, top_line = stack.pop()
                    if top_tag != tag_name:
                        print(f"Error: Mismatched closing tag </{tag_name}> at line {i+1}. Expected </{top_tag}> (opened at line {top_line})")
            else:
                stack.append((tag_name, i+1))
                
    for tag_name, line in stack:
        print(f"Error: Unclosed tag <{tag_name}> opened at line {line}")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python debug_jsx.py <file_path>")
        sys.exit(1)
        
    with open(sys.argv[1], 'r') as f:
        content = f.read()
        find_mismatched_tags(content)
