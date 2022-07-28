import React from 'react';
import Chartist from 'chartist';
import helpers from 'utils/helpers';

class BarChartProjectsByThreshold extends React.Component {

    componentDidMount() {

        const projectsTotal = this.props.data.projectsTotal.overall;
        const below10 = this.props.data.projectsByThreshold.byThreshold.below10;
        const below100 = this.props.data.projectsByThreshold.byThreshold.below100;
        const below1000 = this.props.data.projectsByThreshold.byThreshold.below1000;
        const below10000 = this.props.data.projectsByThreshold.byThreshold.below10000;
        const below25000 = this.props.data.projectsByThreshold.byThreshold.below25000;
        const below50000 = this.props.data.projectsByThreshold.byThreshold.below50000;
        const above50000 = this.props.data.projectsByThreshold.byThreshold.above50000;

        // console.log(projectsTotal, (below10 + below100 + below1000 + below10000 + below25000 + below50000 + above50000));

        const data = {
            labels: [
                '0 to 10  (' + below10 + ' projects - ' + helpers.getPercentage(projectsTotal, below10) + ')',
                '10 to 100  (' + below100 + ' projects - ' + helpers.getPercentage(projectsTotal, below100) + ')',
                '100 to 1K  ('+ below1000 + ' projects - ' + helpers.getPercentage(projectsTotal, below1000) + ')',
                '1K to 10K  ('+ below10000 + ' projects - ' + helpers.getPercentage(projectsTotal, below10000) + ')',
                '10K to 25K  ('+ below25000 + ' projects - ' + helpers.getPercentage(projectsTotal, below25000) + ')',
                '25K to 50K  ('+ below50000 + ' projects - ' + helpers.getPercentage(projectsTotal, below50000) + ')',
                '50K+  ('+ above50000 + ' projects - ' + helpers.getPercentage(projectsTotal, above50000) + ')'
            ],
            series: [
                [
                    below10,
                    below100,
                    below1000,
                    below10000,
                    below25000,
                    below50000,
                    above50000
                ]
            ]
        };

        //show distribution using bar charts
        Object.create(Chartist.Bar(this.barChartNode, data, {

            reverseData: true,
            horizontalBars: true,
            // distributeSeries: true,
            axisY: {
                offset: 300
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
                top: 10,
                right: 40,
                bottom: 10,
                left: 0
            }
        }).on('draw', (barData) => {
            if (barData.type === 'bar') {
                barData.element.attr({
                    style: 'stroke-width: 24px'
                });
            }
        }));
    }

    render() {
        return (
            <div
                className="bar-chart-bythreshold"
                ref={(barChartNode) => {
                    this.barChartNode = barChartNode;
                }}
            />
        );
    }
}

export default BarChartProjectsByThreshold;
