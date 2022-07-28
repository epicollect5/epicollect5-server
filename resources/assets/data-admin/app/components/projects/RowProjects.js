import React from 'react';

import { Row, Col } from 'react-bootstrap';
import TableProjects from 'components/projects/TableProjects';
import BarChartProjects from 'components/projects/BarChartProjects';

const RowProjects = ({ data }) => {

    return (
        <Row className="animated fadeIn">
                    <Col xs={12} md={12} lg={12}>
                        <TableProjects stats={data} />
                    </Col>
                    <Col xs={12} md={12} lg={12}>
                        <BarChartProjects stats={data} />
                    </Col>
                </Row>
    );
};

export default RowProjects;
