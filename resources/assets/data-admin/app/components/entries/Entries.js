import React from 'react';
import fecha from 'fecha';
import { Row, Col, Grid, Panel } from 'react-bootstrap';
import PanelStats from 'components/PanelStats';
import LineChartEntries from 'components/entries/LineChartEntries';
import ErrorView from 'components/ErrorView';
import Loader from 'components/Loader';
import PARAMETERS from 'config/parameters';
import RowPlatformEntries from 'components/entries/RowPlatformEntries';

const Entries = ({ stats }) => {

    const type = PARAMETERS.TYPE_ENTRIES;
    const entriesTotal = stats.entriesTotal;
    const entriesToday = stats.entriesToday;
    const entriesWeek = stats.entriesWeek;
    const entriesMonth = stats.entriesMonth;
    const entriesYear = stats.entriesYear;
    const entriesPlatform = stats.entriesPlatform;
    const entrieByMonth = stats.entriesByMonth;

    const todayDate = new Date();
    const yesterdayDate = new Date(todayDate.setDate(todayDate.getDate() - 1));
    const lastWeekDate = new Date(yesterdayDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    const todayTitle = 'Yesterday, ' + fecha.format(yesterdayDate, 'ddd MMM Do, YYYY');
    const weekTitle = '7-Days, since ' + fecha.format(lastWeekDate, 'ddd MMM Do');
    const monthTitle = 'Month, ' + fecha.format(yesterdayDate, 'MMMM');
    const yearTitle = 'Year, ' + fecha.format(yesterdayDate, 'YYYY');
    const byMonthTitle = 'Monthly Distribution, ' + fecha.format(yesterdayDate, 'YYYY');
    const byPlatformTitle = 'Platform Distribution';

    return (
        <Grid className="entries-stats" fluid>
            <h2 className="page-title">{type}</h2>
            <Row className="">
                <Col xs={12} md={6} lg={6}>
                    <PanelStats data={entriesTotal} title="Total" type={type} />
                </Col>
                <Col xs={12} md={6} lg={6}>
                    <PanelStats data={entriesToday} title={todayTitle} type={type} />
                </Col>
            </Row>

            <Row className="">
                <Col xs={12} lg={4}>
                    <PanelStats data={entriesWeek} title={weekTitle} type={type} />
                </Col>
                <Col xs={12} lg={4}>
                    <PanelStats data={entriesMonth} title={monthTitle} type={type} />
                </Col>
                <Col xs={12} lg={4}>
                    <PanelStats data={entriesYear} title={yearTitle} type={type} />
                </Col>
            </Row>

            <Row className="">
                <Col xs={12} md={12} lg={12}>
                    <Panel className="stats-panel" header={byMonthTitle}>
                        {entrieByMonth.wasRejected === null
                            ?
                            <Loader elementClass={'panel-loader'} />
                            : null
                        }

                        {
                            entrieByMonth.wasRejected === false
                                ? <LineChartEntries data={entrieByMonth} />
                                : null
                        }

                        {entrieByMonth.wasRejected
                            ? <ErrorView data={entrieByMonth} />
                            : null
                        }
                    </Panel>
                </Col>
            </Row>

            <Row className="entries-stats__platform">
                <Col xs={12} md={12} lg={12}>
                    <Panel className="stats-panel" header={byPlatformTitle}>
                        {entriesPlatform.wasRejected === null
                            ?
                            <Loader elementClass={'panel-loader'} />
                            :
                            <RowPlatformEntries data={entriesPlatform} />
                        }
                    </Panel>
                </Col>
            </Row>

        </Grid>
    );
};

export default Entries;
