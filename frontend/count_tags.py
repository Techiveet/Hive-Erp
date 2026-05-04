import sys
import re

def count_tags(content, start_line, end_line):
    lines = content.split('\n')[start_line-1:end_line]
    text = '\n'.join(lines)
    
    # Simple tag counters
    div_open = len(re.findall(r'<div(?![a-zA-Z0-9])', text))
    div_close = len(re.findall(r'</div', text))
    
    motion_div_open = len(re.findall(r'<motion.div', text))
    motion_div_close = len(re.findall(r'</motion.div', text))
    
    card_open = len(re.findall(r'<Card(?![a-zA-Z0-9])', text))
    card_close = len(re.findall(r'</Card>', text))
    
    card_header_open = len(re.findall(r'<CardHeader', text))
    card_header_close = len(re.findall(r'</CardHeader', text))
    
    card_content_open = len(re.findall(r'<CardContent', text))
    card_content_close = len(re.findall(r'</CardContent', text))
    
    responsive_open = len(re.findall(r'<ResponsiveContainer', text))
    responsive_close = len(re.findall(r'</ResponsiveContainer', text))
    
    return {
        'div': (div_open, div_close),
        'motion.div': (motion_div_open, motion_div_close),
        'Card': (card_open, card_close),
        'CardHeader': (card_header_open, card_header_close),
        'CardContent': (card_content_open, card_content_close),
        'ResponsiveContainer': (responsive_open, responsive_close)
    }

if __name__ == "__main__":
    file_path = sys.argv[1]
    with open(file_path, 'r') as f:
        content = f.read()
    
    chunks = [
        (1168, 1413),
        (1414, 1572),
        (1573, 1750),
        (1751, 1917),
        (1918, 2083),
        (2084, 2150),
        (2151, 2372),
        (2373, 2468),
        (2469, 2535)
    ]
    
    for start, end in chunks:
        counts = count_tags(content, start, end)
        print(f"--- Chunk {start}-{end} ---")
        for tag, (opened, closed) in counts.items():
            if opened != closed:
                print(f"  {tag}: {opened} opened, {closed} closed (Diff: {opened - closed})")
        print("")
