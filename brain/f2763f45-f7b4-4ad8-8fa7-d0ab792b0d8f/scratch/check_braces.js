const fs = require('fs');
const content = fs.readFileSync('/home/mike/projects/hive/frontend/modules/projectmanagement/components/ProjectOverviewCharts.tsx', 'utf8');

let balance = 0;
let lines = content.split('\n');

for (let i = 0; i < lines.length; i++) {
    let line = lines[i];
    for (let char of line) {
        if (char === '{') balance++;
        if (char === '}') balance--;
    }
    if (i + 1 === 202) console.log(`Line 202 balance: ${balance}`);
    if (i + 1 === 1073) console.log(`Line 1073 balance: ${balance}`);
    if (i + 1 === 1075) console.log(`Line 1075 balance: ${balance}`);
    if (i + 1 === 1102) console.log(`Line 1102 balance: ${balance}`);
}

console.log(`Final balance: ${balance}`);
