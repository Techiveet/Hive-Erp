const fs = require('fs');

const content = fs.readFileSync('/home/mike/projects/hive/frontend/modules/projectmanagement/components/ProjectOverviewCharts.tsx', 'utf8');

function getLine(text, index) {
    return text.substring(0, index).split('\n').length;
}

function checkTags(text) {
    const stack = [];
    // Handle multi-line tags by using [^] instead of . and allowing newlines
    const tagRegex = /<(\/?[a-zA-Z0-9.]+)(?:\s+[^]*?)?(\/?)>/g;
    let match;
    
    while ((match = tagRegex.exec(text)) !== null) {
        const tagName = match[1];
        const isClosing = tagName.startsWith('/');
        const isSelfClosing = match[2] === '/';
        
        if (isSelfClosing) continue;
        
        if (isClosing) {
            const actualTag = tagName.substring(1);
            if (stack.length === 0) {
                // Ignore extra closing tags if they might be valid (e.g. in strings)
                // But in a large component, they usually aren't.
                console.log(`Extra closing tag: </${actualTag}> at Line ${getLine(text, match.index)}`);
                continue;
            }
            const expectedTag = stack.pop();
            if (actualTag !== expectedTag.name) {
                console.log(`Mismatched closing tag at Line ${getLine(text, match.index)}: expected </${expectedTag.name}> (opened at Line ${getLine(text, expectedTag.index)}), got </${actualTag}>`);
            }
        } else {
            stack.push({ name: tagName, index: match.index });
        }
    }
    
    while (stack.length > 0) {
        const unclosed = stack.pop();
        console.log(`Unclosed tag: <${unclosed.name}> opened at Line ${getLine(text, unclosed.index)}`);
    }
}

checkTags(content);
