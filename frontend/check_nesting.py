import sys
import re

def check_nesting(file_path):
    with open(file_path, 'r') as f:
        content = f.read()

    # Remove comments
    content = re.sub(r'{\/\*.*?\*\/}', '', content, flags=re.DOTALL)
    content = re.sub(r'\/\/.*', '', content)
    
    # Regex to find tags, including multiline
    # Group 1: / (if closing)
    # Group 2: tag name
    # Group 3: attributes (multiline)
    # Group 4: / (if self-closing)
    tag_pattern = re.compile(r'<(/?)([a-zA-Z0-9\.]+)(.*?)(\/?)>', re.DOTALL)
    
    stack = []
    
    # We need to find the line number for each match
    matches = []
    for match in tag_pattern.finditer(content):
        line_num = content.count('\n', 0, match.start()) + 1
        matches.append((match, line_num))
        
    for match, line_num in matches:
        is_closing = match.group(1) == '/'
        tag_name = match.group(2)
        is_self_closing = match.group(4) == '/'
        
        # In JSX, anything ending in /> is self-closing
        # Also check if the attributes end with /
        if not is_self_closing and match.group(3).strip().endswith('/'):
            is_self_closing = True

        if tag_name in ['img', 'br', 'hr', 'input', 'meta', 'link', 'Area', 'Bar', 'Line', 'XAxis', 'YAxis', 'CartesianGrid', 'Tooltip', 'Legend', 'Cell', 'Radar', 'PolarGrid', 'PolarAngleAxis', 'stop', 'Badge']:
            continue
            
        if is_self_closing:
            continue
            
        if is_closing:
            if not stack:
                print(f"Error: Unexpected closing tag </{tag_name}> at line {line_num}")
            else:
                last_tag, last_line = stack.pop()
                if last_tag != tag_name:
                    print(f"Error: Mismatched tag. Expected </{last_tag}> (opened at line {last_line}), got </{tag_name}> at line {line_num}")
                    # Put it back to try to recover? No, just keep going.
        else:
            stack.append((tag_name, line_num))
            
    if stack:
        print("Error: Unclosed tags at end of file:")
        for tag, line in stack:
            print(f"  <{tag}> opened at line {line}")
                
    if stack:
        print("Error: Unclosed tags at end of file:")
        for tag, line in stack:
            print(f"  <{tag}> opened at line {line}")

if __name__ == "__main__":
    check_nesting(sys.argv[1])
