import React from 'react';
import Chartist from 'chartist';

class PieChart extends React.Component {

    componentDidMount() {

        const privateTotal = this.props.stats.private;
        const publicTotal = this.props.stats.public;

        const data = {
            series: [
                privateTotal,
                publicTotal
            ]
        };

        const sum = (a, b) => {
            return a + b;
        };

        const getLabelOffset = () => {

            //if both private or public are 0, shift label up, as only one label will be shown
            if (privateTotal === 0 || publicTotal === 0) {
                return -30;//goes up
            }
            return 0;
        };

        //no data for both? show a placeholder chart (set one sector to 100, remove all the others)
        if (privateTotal === 0 && publicTotal === 0) {
            data.series.push(1);
        }

        let options = {
            labelOffset: getLabelOffset(),
            ignoreEmptyValues: true,
            labelInterpolationFnc: (value) => {

                //if no data, show "N/A" as label for the pie chart
                if (privateTotal === 0 && publicTotal === 0) {
                    return 'N/A';
                }
                return Math.round((value / data.series.reduce(sum)) * 100) + '%';
            }
        };

        if (privateTotal > 0 && publicTotal > 0) {
            options = {
                ...options,
                donut: true,
                donutWidth: 40,
                donutSolid: true,
                startAngle: 270,
                showLabel: true
            };
        }

        //show distribution of public/private entries in percentage
        Object.create(Chartist.Pie(this.pieChartNode, data, options));
    }

    render() {
        return (
            <div
                className="pie-chart"
                ref={(pieChartNode) => { this.pieChartNode = pieChartNode; }}
            />
        );
    }
}

export default PieChart;
