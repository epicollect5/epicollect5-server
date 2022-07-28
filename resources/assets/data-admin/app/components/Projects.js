import React from 'react';
import fecha from 'fecha';
import { Row, Col, Grid } from 'react-bootstrap';
import PanelStats from 'components/PanelStats';

const Projects = ({ stats }) => {

    const projectsTotal = stats.projectsTotal;
    const projectsToday = stats.projectsToday;
    const projectsWeek = stats.projectsWeek;
    const projectsMonth = stats.projectsMonth;
    const projectsYear = stats.projectsYear;

    const todayDate = new Date();
    const yesterdayDate = new Date(todayDate.setDate(todayDate.getDate() - 1));
    const lastWeekDate = new Date(yesterdayDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    const todayTitle = 'Yesterday, ' + fecha.format(yesterdayDate, 'ddd MMM Do, YYYY');
    const weekTitle = 'Week, since ' + fecha.format(lastWeekDate, 'ddd MMM Do');
    const monthTitle = 'Month, ' + fecha.format(yesterdayDate, 'MMMM');
    const yearTitle = 'Year, ' + fecha.format(yesterdayDate, 'YYYY');

    return (
        <Grid className="projects-stats" fluid>
                <h2 className="page-title">Projects</h2>
                <Row className="">
                    <Col xs={12} md={6} lg={6}>
                        <PanelStats data={projectsTotal} title="Total" />
                    </Col>
                    <Col xs={12} md={6} lg={6}>
                        <PanelStats data={projectsToday} title={todayTitle} />
                    </Col>
                </Row>

                <Row className="">
                    <Col xs={12} lg={4}>
                        <PanelStats data={projectsWeek} title={weekTitle} />
                    </Col>
                    <Col xs={12} lg={4}>
                        <PanelStats data={projectsMonth} title={monthTitle} />
                    </Col>
                    <Col xs={12} lg={4}>
                        <PanelStats data={projectsYear} title={yearTitle} />
                    </Col>
                </Row>
            </Grid>
    );
};

export default Projects;
