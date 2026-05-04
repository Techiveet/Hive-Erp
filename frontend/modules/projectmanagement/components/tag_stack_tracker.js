
const fs = require('fs');

const content = fs.readFileSync('/home/mike/projects/hive/frontend/modules/projectmanagement/components/ProjectOverviewCharts.tsx', 'utf8');

const lines = content.split('\n');
const stack = [];

const tagsToTrack = ['div', 'motion.div', 'Card', 'CardHeader', 'CardTitle', 'CardDescription', 'CardContent'];

lines.forEach((line, index) => {
    const lineNumber = index + 1;
    
    // Simple regex to find tags
    // This is a bit naive but might work for well-formed JSX
    const tagRegex = /<(\/?)(div|motion\.div|Card|CardHeader|CardTitle|CardDescription|CardContent)([\s\>])|(\/>)/g;
    let match;
    
    while ((match = tagRegex.exec(line)) !== null) {
        const isClosing = match[1] === '/';
        const tagName = match[2];
        const isSelfClosing = match[4] === '/>';
        
        if (isSelfClosing) {
            // Self-closing doesn't affect stack
            continue;
        }
        
        if (!tagName) continue;

        if (isClosing) {
            if (stack.length === 0) {
                console.log(`Error: Found closing tag </${tagName}> at line ${lineNumber} but stack is empty`);
            } else {
                const last = stack.pop();
                if (last.name !== tagName) {
                    console.log(`Error: Mismatched tag at line ${lineNumber}. Expected </${last.name}> (opened at line ${last.line}), but found </${tagName}>`);
                }
            }
        } else {
            stack.push({ name: tagName, line: lineNumber });
        }
    }
});

if (stack.length > 0) {
    console.log('Unclosed tags:');
    stack.forEach(tag => {
        console.log(`- <${tag.name}> opened at line ${tag.line}`);
    });
} else {
    console.log('All tracked tags are balanced!');
}
