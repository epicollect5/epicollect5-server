import React from 'react';
import Chartist from 'chartist';
import helpers from 'utils/helpers';

class BarChartPlatformEntries extends React.Component {

    componentDidMount() {

        const android = this.props.stats.android;
        const ios = this.props.stats.ios;
        const web = this.props.stats.web;
        const unknown = this.props.stats.unknown;

        const data = {
            labels: ['Android', 'iOS', 'Web', 'Unknown'],
            series: [[
                android,
                ios,
                web,
                unknown
            ]]
        };



        //show distribution of public/private entries in percentage
        Object.create(Chartist.Bar(this.barChartNode, data, {
            seriesBarDistance: 10,
            reverseData: true,
            horizontalBars: true,
            axisY: {
                offset: 80
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
                right: 40,
                bottom: 0,
                left: 0
            }
        }).on('draw', (barData) => {

            if (barData.type === 'bar') {
                barData.element.attr({
                    style: 'stroke-width: 16px'
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

export default BarChartPlatformEntries;
