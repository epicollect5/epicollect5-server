import React from 'react';
import Chartist from 'chartist';
import helpers from 'utils/helpers';

class BarChartUsers extends React.Component {

    componentDidMount() {
        const usersByMonth = this.props.data.byMonth;

        const data = {
            labels: usersByMonth.map((month) => {
                return Object.keys(month)[0];
            }),
            series: [usersByMonth.map((month) => {
                return month[Object.keys(month)];
            })]
        };

        //show distribution using bar charts
        Object.create(Chartist.Line(this.barChartNode, data, {
            distributeSeries: true,
            axisY: {
                labelInterpolationFnc: (value) => {
                    return helpers.makeFriendlyNumber(value);
                }
            }
        }).on('draw', (barData) => {
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

export default BarChartUsers;
