import React from 'react';
import Chartist from 'chartist';

class LineChartProjects extends React.Component {

    componentDidMount() {
        const projectsByMonth = this.props.data.byMonth;

        const data = {
            labels: projectsByMonth.map((month) => {
                return Object.keys(month)[0];
            }),
            series: [projectsByMonth.map((month) => {
                return month[Object.keys(month)];
            })]
        };

        //show distribution using bar charts
        Object.create(Chartist.Line(this.lineChartNode, data, {
        }).on('draw', () => {
        }));
    }

    render() {
        return (
            <div
                className="line-chart"
                ref={(lineChartNode) => { this.lineChartNode = lineChartNode; }}
            />
        );
    }
}

export default LineChartProjects;
