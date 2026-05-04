
const fs = require('fs');

const content = fs.readFileSync('/home/mike/projects/hive/frontend/modules/projectmanagement/components/ProjectOverviewCharts.tsx', 'utf8');

const tags = [
    'div',
    'motion.div',
    'Card',
    'CardHeader',
    'CardTitle',
    'CardDescription',
    'CardContent',
    'ResponsiveContainer',
    'LineChart',
    'BarChart',
    'AreaChart',
    'RadarChart',
    'ScatterChart',
    'ComposedChart',
    'XAxis',
    'YAxis',
    'ZAxis',
    'Tooltip',
    'Legend',
    'CartesianGrid',
    'Line',
    'Bar',
    'Area',
    'Radar',
    'Scatter',
    'Cell',
    'PolarGrid',
    'PolarAngleAxis',
    'Badge',
    'Link',
    'Button',
    'Tabs',
    'TabsList',
    'TabsTrigger',
    'TabsContent',
];

const results = {};

tags.forEach(tag => {
    const openRegex = new RegExp(`<${tag}(\\s|>)`, 'g');
    const closeRegex = new RegExp(`</${tag}>`, 'g');
    const selfCloseRegex = new RegExp(`<${tag}[^>]*/>`, 'g');

    const openMatches = content.match(openRegex) || [];
    const closeMatches = content.match(closeRegex) || [];
    const selfCloseMatches = content.match(selfCloseRegex) || [];

    results[tag] = {
        open: openMatches.length,
        close: closeMatches.length,
        selfClose: selfCloseMatches.length,
        balance: openMatches.length - closeMatches.length - selfCloseMatches.length
    };
});

console.log(JSON.stringify(results, null, 2));
