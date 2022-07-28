import React from 'react';
import { Row, Col, Grid, Panel } from 'react-bootstrap';
import fecha from 'fecha';
import Loader from 'components/Loader';
import PanelStats from 'components/PanelStats';
import BarChartUsers from 'components/users/BarChartUsers';
import PARAMETERS from 'config/parameters';
import ErrorView from 'components/ErrorView';

const Users = ({ stats }) => {

    const type = PARAMETERS.TYPE_USERS;
    const todayDate = new Date();
    const yesterdayDate = new Date(todayDate.setDate(todayDate.getDate() - 1));

    const panelHeader = (
        <h3>Distribution by month, {fecha.format(yesterdayDate, 'YYYY')}</h3>
    );

    return (
        <Grid className="users-stats" fluid>
                <h2 className="page-title">{type}</h2>
                <Row className="">
                    <Col xs={12} sm={12} md={4} lg={4}>
                        <PanelStats data={stats} title="Total" type={type} />
                    </Col>
                    <Col xs={12} sm={12} md={8} lg={8}>
                            <Panel className="stats-panel" header={panelHeader}>
                                { stats.wasRejected === null
                                    ?
                                    <Loader elementClass={'panel-loader'} />
                                    : null
                                }

                                { stats.wasRejected === false
                                    ?
                                    <BarChartUsers data={stats} />
                                    : null
                                }

                                {stats.wasRejected
                                    ? <ErrorView data={stats} />
                                    : null
                                }
                            </Panel>
                          </Col>
                </Row>
            </Grid>
    );
};

export default Users;
