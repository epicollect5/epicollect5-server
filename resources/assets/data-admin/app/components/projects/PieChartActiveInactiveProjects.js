import React from 'react';
import Chartist from 'chartist';

class PieChartActiveInactiveProjects extends React.Component {

    componentDidMount() {

        const projectsTotal =  this.props.data.projectsTotal.overall;
        const thresholdData = this.props.data.projectsByThreshold.byThreshold;
        const inactiveTotal = thresholdData.below10;
        const activeTotal = projectsTotal - inactiveTotal;

        const data = {
            series: [
                inactiveTotal,
                activeTotal
            ]
        };

        const sum = (a, b) => {
            return a + b;
        };

        const getLabelOffset = () => {

            //if both private or public are 0, shift label up, as only one label will be shown
            if (activeTotal === 0 || inactiveTotal === 0) {
                return -30;//goes up
            }
            return 0;
        };

        //no data for both? show a placeholder chart (set one sector to 100, remove all the others)
        if (inactiveTotal === 0 && activeTotal === 0) {
            data.series.push(1);
        }

        let options = {
            labelOffset: getLabelOffset(),
            ignoreEmptyValues: true,
            labelInterpolationFnc: (value) => {

                //if no data, show "N/A" as label for the pie chart
                if (activeTotal === 0 && inactiveTotal === 0) {
                    return 'N/A';
                }

                return Math.round((value / data.series.reduce(sum)) * 100) + '%';
            }
        };

        if (activeTotal > 0 && inactiveTotal > 0) {
            options = {
                ...options,
                donut: true,
                donutWidth: 50,
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
                className="pie-chart__active-inactive-projects"
                ref={(pieChartNode) => { this.pieChartNode = pieChartNode; }}
            />
        );
    }
}

export default PieChartActiveInactiveProjects;
