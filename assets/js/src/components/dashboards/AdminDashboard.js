import React from 'react';
import { 
    Grid, 
    Paper, 
    Typography,
    Box,
    Card,
    CardContent
} from '@mui/material';
import { useQuery } from 'react-query';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell
} from 'recharts';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042'];

const AdminDashboard = () => {
    const { data: stats, isLoading } = useQuery('adminStats', () =>
        fetch('/wp-json/hospital-manager/v1/stats').then(res => res.json())
    );

    if (isLoading) return <div>Loading...</div>;

    return (
        <Box sx={{ flexGrow: 1, p: 3 }}>
            <Grid container spacing={3}>
                {/* Summary Cards */}
                <Grid item xs={12} md={3}>
                    <Card>
                        <CardContent>
                            <Typography color="textSecondary" gutterBottom>
                                Total Patients
                            </Typography>
                            <Typography variant="h4">
                                {stats?.totalPatients || 0}
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12} md={3}>
                    <Card>
                        <CardContent>
                            <Typography color="textSecondary" gutterBottom>
                                Active Doctors
                            </Typography>
                            <Typography variant="h4">
                                {stats?.activeDoctors || 0}
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12} md={3}>
                    <Card>
                        <CardContent>
                            <Typography color="textSecondary" gutterBottom>
                                Today's Visitations
                            </Typography>
                            <Typography variant="h4">
                                {stats?.todayVisitations || 0}
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12} md={3}>
                    <Card>
                        <CardContent>
                            <Typography color="textSecondary" gutterBottom>
                                Pending Lab Tests
                            </Typography>
                            <Typography variant="h4">
                                {stats?.pendingLabTests || 0}
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Visitations Trend */}
                <Grid item xs={12} md={8}>
                    <Paper sx={{ p: 2 }}>
                        <Typography variant="h6" gutterBottom>
                            Visitations Trend
                        </Typography>
                        <LineChart
                            width={700}
                            height={300}
                            data={stats?.visitationsTrend || []}
                            margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="date" />
                            <YAxis />
                            <Tooltip />
                            <Legend />
                            <Line 
                                type="monotone" 
                                dataKey="count" 
                                stroke="#8884d8" 
                                name="Visitations"
                            />
                        </LineChart>
                    </Paper>
                </Grid>

                {/* HMO Distribution */}
                <Grid item xs={12} md={4}>
                    <Paper sx={{ p: 2 }}>
                        <Typography variant="h6" gutterBottom>
                            Patients by HMO
                        </Typography>
                        <PieChart width={300} height={300}>
                            <Pie
                                data={stats?.patientsByHMO || []}
                                cx={150}
                                cy={150}
                                labelLine={false}
                                outerRadius={100}
                                fill="#8884d8"
                                dataKey="value"
                                label={({name, percent}) => `${name} ${(percent * 100).toFixed(0)}%`}
                            >
                                {(stats?.patientsByHMO || []).map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip />
                        </PieChart>
                    </Paper>
                </Grid>

                {/* Monthly Lab Tests */}
                <Grid item xs={12}>
                    <Paper sx={{ p: 2 }}>
                        <Typography variant="h6" gutterBottom>
                            Monthly Lab Tests
                        </Typography>
                        <BarChart
                            width={1000}
                            height={300}
                            data={stats?.monthlyLabTests || []}
                            margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" />
                            <YAxis />
                            <Tooltip />
                            <Legend />
                            <Bar dataKey="completed" name="Completed" fill="#82ca9d" />
                            <Bar dataKey="pending" name="Pending" fill="#ffc658" />
                        </BarChart>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default AdminDashboard;
