import React from 'react';
import fecha from 'fecha';
import { Row, Col, Grid, Panel } from 'react-bootstrap';
import PanelStats from 'components/PanelStats';
import LineChartProjects from 'components/projects/LineChartProjects';
import Loader from 'components/Loader';
import ErrorView from 'components/ErrorView';
import PARAMETERS from 'config/parameters';
import BarChartProjectsByThreshold from 'components/projects/BarChartProjectsByThreshold';
import PieChartActiveInactiveProjects from 'components/projects/PieChartActiveInactiveProjects';
import TableProjectsActiveInactive from 'components/projects/TableProjectsActiveInactive';

const Projects = ({ stats }) => {

    const type = PARAMETERS.TYPE_PROJECTS;
    const projectsTotal = stats.projectsTotal;
    const projectsToday = stats.projectsToday;
    const projectsWeek = stats.projectsWeek;
    const projectsMonth = stats.projectsMonth;
    const projectsByMonth = stats.projectsByMonth;
    const projectsByThreshold = stats.projectsByThreshold;
    const projectsYear = stats.projectsYear;

    const todayDate = new Date();
    const yesterdayDate = new Date(todayDate.setDate(todayDate.getDate() - 1));
    const lastWeekDate = new Date(yesterdayDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    const todayTitle = 'Yesterday, ' + fecha.format(yesterdayDate, 'ddd MMM Do, YYYY');
    const weekTitle = '7-Days, since ' + fecha.format(lastWeekDate, 'ddd MMM Do');
    const monthTitle = 'Month, ' + fecha.format(yesterdayDate, 'MMMM');
    const yearTitle = 'Year, ' + fecha.format(yesterdayDate, 'YYYY');
    const byMonthTitle = 'Monthly Distribution, ' + fecha.format(yesterdayDate, 'YYYY');
    const byThresholdTitle = 'Distribution by Entries Total';
    const activeInactiveRatio = 'Usage Ratio';

    return (
        <Grid className="projects-stats" fluid>
            <h2 className="page-title">{type}</h2>
            <Row className="">
                <Col xs={12} md={6} lg={6}>
                    <PanelStats data={projectsTotal} title="Total" type={type} />
                </Col>
                <Col xs={12} md={6} lg={6}>
                    <PanelStats data={projectsToday} title={todayTitle} type={type} />
                </Col>
            </Row>

            <Row className="">
                <Col xs={12} lg={4}>
                    <PanelStats data={projectsWeek} title={weekTitle} type={type} />
                </Col>
                <Col xs={12} lg={4}>
                    <PanelStats data={projectsMonth} title={monthTitle} type={type} />
                </Col>
                <Col xs={12} lg={4}>
                    <PanelStats data={projectsYear} title={yearTitle} type={type} />
                </Col>
            </Row>
            <Row className="">
                <Col xs={12} md={12} lg={12}>
                    <Panel className="stats-panel" header={byMonthTitle}>
                        {projectsByMonth.wasRejected === null
                            ?
                            <Loader elementClass={'panel-loader'} />
                            : null
                        }

                        {
                            projectsByMonth.wasRejected === false
                                ? <LineChartProjects data={projectsByMonth} />
                                : null
                        }

                        {projectsByMonth.wasRejected
                            ? <ErrorView data={projectsByMonth} />
                            : null
                        }
                    </Panel>
                </Col>
            </Row>
            <Row className="">
                <Col xs={6} md={6} lg={6}>
                    <Panel className="stats-panel" header={byThresholdTitle}>
                        {projectsByThreshold.wasRejected === null
                            ?
                            <Loader elementClass={'panel-loader'} />
                            : null
                        }

                        {
                            projectsByThreshold.wasRejected === false
                                ? <BarChartProjectsByThreshold
                                    data={{ projectsByThreshold, projectsTotal }}
                                />
                                : null
                        }

                        {projectsByThreshold.wasRejected
                            ? <ErrorView data={projectsByThreshold} />
                            : null
                        }
                    </Panel>
                </Col>
                <Col xs={6} md={6} lg={6}>
                    <Panel className="stats-panel projects-stats__active-inactive" header={activeInactiveRatio}>
                        {projectsByThreshold.wasRejected === null
                            ?
                            <Loader elementClass={'panel-loader'} />
                            : null
                        }

                        {
                            projectsByThreshold.wasRejected === false
                                ?<div>
                                    <TableProjectsActiveInactive
                                        stats={{ projectsByThreshold, projectsTotal }}
                                    />
                                    <PieChartActiveInactiveProjects
                                        data={{ projectsByThreshold, projectsTotal }}
                                    />
                                </div>
                                : null
                        }

                        {projectsByThreshold.wasRejected
                            ? <ErrorView data={projectsByThreshold} />
                            : null
                        }
                    </Panel>
                </Col>
            </Row>
        </Grid>
    );
};

export default Projects;
