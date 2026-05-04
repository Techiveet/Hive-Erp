const fs = require('fs');

const content = fs.readFileSync('/home/mike/projects/hive/frontend/modules/projectmanagement/components/ProjectOverviewCharts.tsx', 'utf8');

function checkBalance(text) {
    let braceCount = 0;
    let parenCount = 0;
    let bracketCount = 0;
    
    for (let i = 0; i < text.length; i++) {
        if (text[i] === '{') braceCount++;
        if (text[i] === '}') braceCount--;
        if (text[i] === '(') parenCount++;
        if (text[i] === ')') parenCount--;
        if (text[i] === '[') bracketCount++;
        if (text[i] === ']') bracketCount--;
        
        if (braceCount < 0) console.log(`Extra } at char ${i}`);
        if (parenCount < 0) console.log(`Extra ) at char ${i}`);
        if (bracketCount < 0) console.log(`Extra ] at char ${i}`);
    }
    
    console.log(`Final counts - Braces: ${braceCount}, Parens: ${parenCount}, Brackets: ${bracketCount}`);
}

checkBalance(content);
