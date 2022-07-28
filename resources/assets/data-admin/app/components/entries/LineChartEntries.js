import React from 'react';
import Chartist from 'chartist';
import helpers from 'utils/helpers';

class LineChartEntries extends React.Component {

    componentDidMount() {
        const entriesByMonth = this.props.data.byMonth;

        const data = {
            labels: entriesByMonth.map((month) => {
                return Object.keys(month)[0];
            }),
            series: [entriesByMonth.map((month) => {
                return month[Object.keys(month)];
            })]
        };

        //show distribution using bar charts
        Object.create(Chartist.Line(this.lineChartNode, data, {
            axisY: {
                labelInterpolationFnc: (value) => {
                    return helpers.makeFriendlyNumber(value);
                }
            }
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

export default LineChartEntries;
