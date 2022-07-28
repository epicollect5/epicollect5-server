import React from 'react';
import Chartist from 'chartist';
import helpers from 'utils/helpers';

class BarChartProjects extends React.Component {

    componentDidMount() {
        const privateProjects = this.props.stats.private;
        const publicProjects = this.props.stats.public;

        const privateProjectsTotal = this.props.stats.private.listed + this.props.stats.private.hidden;
        const publicProjectsTotal = this.props.stats.public.listed + this.props.stats.public.hidden;
        const overallProjects = privateProjectsTotal + publicProjectsTotal;
        const privateProjectsPercentage = helpers.getPercentage(overallProjects, privateProjectsTotal);
        const publicProjectsPercentage = helpers.getPercentage(overallProjects, publicProjectsTotal);

        const data = {
            labels: [privateProjectsPercentage + ' Private', publicProjectsPercentage + ' Public'],
            series: [
                [privateProjects.hidden, publicProjects.hidden],
                [privateProjects.listed, publicProjects.listed]
            ]
        };

        //show distribution using bar charts
        Object.create(Chartist.Bar(this.barChartNode, data, {
            stackBars: true,
            seriesBarDistance: 10,
            reverseData: true,
            horizontalBars: true,
            axisY: {
                offset: 60
            },
            axisX: {
                labelInterpolationFnc: (value, index) => {

                    if (index % 2 === 0) {
                        if (value <= 1) {
                            return Math.round10(value, -1);
                        }

                        return helpers.makeFriendlyNumber(value);
                    }
                    return null;
                }
            },
            chartPadding: {
                top: 0,
                right: 50,
                bottom: 0,
                left: 0
            }
        }).on('draw', (barData) => {
            if (barData.type === 'bar') {
                barData.element.attr({
                    style: 'stroke-width: 30px'
                });
            }
        }));
    }

    render() {
        return (
            <div
                className="bar-chart"
                ref={(barChartNode) => { this.barChartNode = barChartNode; }}
            />
        );
    }
}

export default BarChartProjects;
